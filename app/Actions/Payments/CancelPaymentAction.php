<?php

namespace App\Actions\Payments;

use App\Enums\EventType;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Payment;
use App\Services\ClientScoreService;
use App\Services\LateFeeService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelPaymentAction
{
    public function __construct(
        private readonly ClientScoreService $scoreService,
        private readonly LateFeeService $lateFee,
    ) {}

    public function execute(Payment $payment, string $reason): Payment
    {
        if ($payment->isCancelled()) {
            throw new RuntimeException('El pago ya está anulado.');
        }

        if ($payment->isPrepayment()) {
            throw new RuntimeException('No se puede anular un pago de anticipo; regenerá el plan manualmente.');
        }

        return DB::transaction(function () use ($payment, $reason) {
            $installment = $payment->installment;
            $loan = $payment->loan;

            $payment->update([
                'status' => PaymentStatus::Cancelled,
                'cancellation_reason' => $reason,
                'updated_by' => auth()->id(),
            ]);

            // Revertir en el mismo orden en que se cobró: la porción de mora
            // vuelve a la cuota y solo el resto devolvió capital al préstamo.
            $feeReversal = (float) $payment->late_fee_amount;
            $balanceReversal = (float) $payment->amount - $feeReversal;

            $newPaid = (float) $installment->paid_amount - $balanceReversal;
            $newPending = (float) $installment->expected_amount - $newPaid;

            $installment->paid_amount = max(0, $newPaid);
            $installment->pending_balance = max(0, $newPending);

            // La mora es un devengue recalculado, no un contador acumulativo:
            // se toma el mayor entre lo que volvería a devengarse con el saldo
            // restaurado y lo que se había cobrado, para no duplicar si el
            // barrido diario ya la había rehecho entre el pago y la anulación.
            $installment->late_fee = max(
                $this->lateFee->calculate($installment),
                (float) $installment->late_fee + $feeReversal,
            );

            $installment->status = $newPending <= 0
                ? InstallmentStatus::Paid
                : ($newPaid > 0 ? InstallmentStatus::Partial : InstallmentStatus::Pending);

            $installment->save();

            $loan->increment('pending_balance', $balanceReversal);

            // Solo vuelve a estar activo si todavía debe capital: el cobro de
            // una mora puntual no reabre un préstamo ya saldado.
            if ((float) $loan->pending_balance > 0) {
                $loan->update(['status' => 'active']);
            }

            Event::create([
                'event_type' => EventType::PaymentCancelled,
                'client_id' => $payment->client_id,
                'loan_id' => $payment->loan_id,
                'payment_id' => $payment->id,
                'extra_data' => ['reason' => $reason, 'amount' => $payment->amount],
                'created_at' => now(),
                'created_by' => auth()->id(),
            ]);

            $this->scoreService->update($loan->client);

            return $payment->fresh();
        });
    }
}
