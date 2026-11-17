<?php

namespace App\Actions\Payments;

use App\Enums\EventType;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Services\ClientScoreService;
use App\Services\LateFeeService;
use App\Services\ReceiptNumberService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RegisterPaymentAction
{
    public function __construct(
        private readonly ReceiptNumberService $receiptService,
        private readonly ClientScoreService $scoreService,
        private readonly LateFeeService $lateFee,
    ) {}

    /**
     * @param  array{amount: float|string, payment_date: string, method: string, notes?: ?string, comprobante_path?: ?string, verification_status?: string, verified_at?: mixed, verified_by?: ?int, user_id?: ?int}  $data
     *
     * @throws RuntimeException when the payment cannot be applied to the loan
     */
    public function execute(Installment $installment, array $data): Payment
    {
        $userId = $data['user_id'] ?? auth()->id();
        $amount = (float) $data['amount'];

        // Las dos entradas HTTP ya lo validan, pero la acción es el único
        // punto por el que pasa todo cobro: un monto no positivo no puede
        // generar un pago de cero ni un sobrepago negativo.
        if ($amount <= 0) {
            throw new RuntimeException('El monto del pago debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($installment, $data, $userId, $amount) {
            // Se relee dentro de la transacción para no decidir con un estado
            // que quedó viejo entre el formulario y el cobro.
            $loan = $installment->loan()->first();

            if ($loan === null) {
                throw new RuntimeException('La cuota no tiene un préstamo asociado.');
            }

            // Un préstamo cancelado o refinanciado ya cerró su plan de cuotas:
            // cualquier cobro contra él dejaría el saldo en negativo.
            if (in_array($loan->status, [LoanStatus::Cancelled, LoanStatus::Refinanced], true)) {
                throw new RuntimeException(
                    'El préstamo está '.mb_strtolower($loan->status->label()).' y no admite cobros.'
                );
            }

            $remaining = $amount;
            $mainPayment = null;

            // Aplicar el monto a las cuotas en orden cronológico. El scope
            // incluye las cuotas con mora devengada aunque su capital esté
            // saldado, porque ese interés sigue siendo cobrable.
            $installments = $loan->installments()
                ->collectable()
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();

            // Empezar desde la cuota seleccionada. Si no está entre las
            // cobrables (ya saldada, anulada o de otro préstamo), se aborta:
            // slice(false) cae en el índice 0 y cobraría la cuota equivocada
            // sin avisar.
            $startIndex = $installments->search(
                fn (Installment $candidate): bool => $candidate->id === $installment->id
            );

            if ($startIndex === false) {
                throw new RuntimeException('La cuota seleccionada no tiene saldo pendiente de cobro.');
            }

            $installments = $installments->slice($startIndex);

            foreach ($installments as $current) {
                if ($remaining <= 0) {
                    break;
                }

                $payment = $this->applyPayment(
                    installment: $current,
                    amount: $remaining,
                    data: $data,
                    loan: $loan,
                    userId: $userId,
                );

                $mainPayment ??= $payment;
                $remaining -= (float) $payment->amount;
            }

            // Sin ningún pago aplicado no hay evento que registrar: se corta
            // acá en lugar de reventar con un null al armar el Event.
            if ($mainPayment === null) {
                throw new RuntimeException('El pago no pudo aplicarse a ninguna cuota.');
            }

            // Si sobra dinero después de cubrir todas las cuotas, el excedente
            // queda como saldo a favor anotado en el primer pago.
            if ($remaining > 0) {
                $mainPayment->update([
                    'amount' => (float) $mainPayment->amount + $remaining,
                    'notes' => trim(($mainPayment->notes ?? '').' [Incluye saldo a favor]'),
                ]);
            }

            $this->updateLoanStatus($loan->fresh(), $userId);

            $this->scoreService->update($loan->client);

            Event::create([
                'event_type' => EventType::PaymentRegistered,
                'client_id' => $loan->client_id,
                'loan_id' => $loan->id,
                'installment_id' => $mainPayment->installment_id,
                'payment_id' => $mainPayment->id,
                'extra_data' => [
                    'amount' => $data['amount'],
                    'late_fee' => (float) $mainPayment->late_fee_amount,
                    'method' => $data['method'],
                    'payment_date' => $data['payment_date'],
                ],
                'created_at' => now(),
                'created_by' => $userId,
            ]);

            return $mainPayment->load(['installment', 'loan']);
        });
    }

    /**
     * Aplica el monto a una cuota: primero salda la mora devengada y solo lo
     * que sobra amortiza capital. El saldo del préstamo solo se reduce con la
     * porción de capital, porque la mora es ingreso y no devuelve deuda.
     */
    private function applyPayment(Installment $installment, float $amount, array $data, Loan $loan, ?int $userId): Payment
    {
        $feeDue = $this->lateFee->refresh($installment, Carbon::parse($data['payment_date']));
        $feeApplied = min($amount, $feeDue);
        $balanceApplied = min($amount - $feeApplied, (float) $installment->pending_balance);

        $newPaid = (float) $installment->paid_amount + $balanceApplied;
        $newPending = (float) $installment->expected_amount - $newPaid;

        $installment->update([
            'paid_amount' => $newPaid,
            'pending_balance' => max(0, $newPending),
            'late_fee' => max(0, $feeDue - $feeApplied),
            // Si el pago solo cubrió mora, la cuota conserva su estado
            // (sigue vencida) hasta que se amortice capital.
            'status' => $newPending <= 0
                ? InstallmentStatus::Paid
                : ($newPaid > 0 ? InstallmentStatus::Partial : $installment->status),
        ]);

        $loan->decrement('pending_balance', $balanceApplied);

        return Payment::create([
            'installment_id' => $installment->id,
            'loan_id' => $loan->id,
            'client_id' => $loan->client_id,
            'receipt_number' => $this->receiptService->next(),
            'amount' => $feeApplied + $balanceApplied,
            'late_fee_amount' => $feeApplied,
            'payment_date' => $data['payment_date'],
            'method' => $data['method'],
            'notes' => $data['notes'] ?? null,
            'comprobante_path' => $data['comprobante_path'] ?? null,
            'verification_status' => $data['verification_status'] ?? 'verificado',
            'verified_at' => $data['verified_at'] ?? null,
            'verified_by' => $data['verified_by'] ?? null,
            'status' => PaymentStatus::Active,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function updateLoanStatus(Loan $loan, ?int $userId): void
    {
        if ($loan->pending_balance <= 0) {
            // Solo el primer cierre deja constancia: cobrar la mora que quedó
            // pendiente sobre un préstamo ya saldado no cierra nada nuevo.
            if ($loan->status !== LoanStatus::Paid) {
                $loan->update(['status' => LoanStatus::Paid]);

                Event::create([
                    'event_type' => EventType::LoanClosed,
                    'client_id' => $loan->client_id,
                    'loan_id' => $loan->id,
                    'created_at' => now(),
                    'created_by' => $userId,
                ]);
            }

            return;
        }

        if ($loan->installments()->overdue()->exists()) {
            $loan->update(['status' => LoanStatus::Overdue]);
        }
    }
}
