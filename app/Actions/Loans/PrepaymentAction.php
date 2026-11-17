<?php

namespace App\Actions\Loans;

use App\Enums\EventType;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Loan;
use App\Models\Payment;
use App\Services\AmortizationService;
use App\Services\ClientScoreService;
use App\Services\ReceiptNumberService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PrepaymentAction
{
    private const CANCELABLE_STATUSES = [
        InstallmentStatus::Pending,
        InstallmentStatus::Partial,
        InstallmentStatus::Overdue,
    ];

    public function __construct(
        private readonly AmortizationService $amortization,
        private readonly ReceiptNumberService $receiptService,
        private readonly ClientScoreService $scoreService,
    ) {}

    public function execute(Loan $loan, array $data): Payment
    {
        $amount = (float) $data['amount'];

        if (! $loan->status->isEditable()) {
            throw new RuntimeException('Solo se pueden anticipar préstamos activos o vencidos.');
        }

        $pendingBalance = (float) $loan->pending_balance;

        if ($pendingBalance <= 0) {
            throw new RuntimeException('El préstamo no tiene saldo pendiente para anticipar.');
        }

        if ($amount >= $pendingBalance) {
            throw new RuntimeException('El anticipo debe ser menor al saldo pendiente. Usá el pago normal si cubre todo el saldo.');
        }

        return DB::transaction(function () use ($loan, $data, $amount, $pendingBalance) {
            $cancelable = $loan->installments()
                ->whereIn('status', array_column(self::CANCELABLE_STATUSES, 'value'))
                ->orderBy('installment_number')
                ->get();

            // Todo lo cobrado hasta hoy (cuotas pagadas y parciales) es
            // historial que el total del préstamo debe seguir incluyendo.
            $paidHistory = (float) $loan->installments()->sum('paid_amount');

            // Las cuotas impagas se borran y se reemplazan por el plan nuevo.
            // Las parciales quedan como historial cancelado, porque sus pagos
            // las referencian por FK y no se pueden eliminar.
            $kept = [];
            $keptNumbers = [];
            foreach ($cancelable as $installment) {
                if ($loan->payments()->where('installment_id', $installment->id)->exists()) {
                    $installment->update(['status' => InstallmentStatus::Cancelled]);
                    $kept[] = $installment;
                    $keptNumbers[] = $installment->installment_number;
                } else {
                    $installment->delete();
                }
            }

            // El nuevo plan nace desde hoy con el capital restante después del
            // anticipo y con las mismas condiciones de tasa del préstamo.
            $plan = $this->amortization->calculate(
                principal: $pendingBalance - $amount,
                interestRate: (float) $loan->interest_rate,
                rateType: $loan->rate_type,
                model: $loan->amortization_model,
                installments: $cancelable->count(),
                startDate: today()->toDateString(),
                frequency: $loan->frequency,
            );

            $number = 1;
            foreach ($plan['installments'] as $installmentPlan) {
                // Si alguna parcial quedó como historial ocupando un número,
                // el plan nuevo saltea ese número para no colisionar.
                while (in_array($number, $keptNumbers, true)) {
                    $number++;
                }

                $loan->installments()->create([
                    'installment_number' => $number,
                    'due_date' => $installmentPlan['due_date'],
                    'expected_amount' => $installmentPlan['amount'],
                    'paid_amount' => 0,
                    'pending_balance' => $installmentPlan['amount'],
                    'status' => InstallmentStatus::Pending,
                ]);

                $number++;
            }

            $payment = Payment::create([
                'installment_id' => null,
                'loan_id' => $loan->id,
                'client_id' => $loan->client_id,
                'receipt_number' => $this->receiptService->next(),
                'amount' => $amount,
                'late_fee_amount' => 0,
                'payment_date' => $data['payment_date'],
                'method' => $data['method'],
                'kind' => Payment::KIND_PREPAYMENT,
                'notes' => $data['notes'] ?? null,
                'status' => PaymentStatus::Active,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $loan->update([
                'total_amount' => $paidHistory + $amount + (float) $plan['total_amount'],
                'pending_balance' => $plan['total_amount'],
                'status' => LoanStatus::Active,
                'updated_by' => auth()->id(),
            ]);

            Event::create([
                'event_type' => EventType::InstallmentGenerated,
                'client_id' => $loan->client_id,
                'loan_id' => $loan->id,
                'extra_data' => [
                    'installments' => $cancelable->count(),
                    'total_amount' => $plan['total_amount'],
                ],
                'created_at' => now(),
                'created_by' => auth()->id(),
            ]);

            Event::create([
                'event_type' => EventType::PaymentRegistered,
                'client_id' => $loan->client_id,
                'loan_id' => $loan->id,
                'installment_id' => null,
                'payment_id' => $payment->id,
                'extra_data' => [
                    'kind' => Payment::KIND_PREPAYMENT,
                    'amount' => $amount,
                    'method' => $data['method'],
                    'payment_date' => $data['payment_date'],
                ],
                'created_at' => now(),
                'created_by' => auth()->id(),
            ]);

            $this->scoreService->update($loan->client);

            return $payment->load('loan');
        });
    }
}
