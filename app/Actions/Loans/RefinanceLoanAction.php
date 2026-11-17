<?php

namespace App\Actions\Loans;

use App\Enums\EventType;
use App\Enums\LoanStatus;
use App\Models\Event;
use App\Models\Loan;
use App\Services\ClientScoreService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RefinanceLoanAction
{
    public function __construct(
        private readonly CreateLoanAction $createLoan,
        private readonly ClientScoreService $scoreService,
    ) {}

    public function execute(Loan $loan, array $data): Loan
    {
        if (! $loan->status->isEditable()) {
            throw new RuntimeException('Solo se pueden refinanciar préstamos activos o vencidos.');
        }

        $balance = (float) $loan->pending_balance;

        if ($balance <= 0) {
            throw new RuntimeException('El préstamo no tiene saldo pendiente para refinanciar.');
        }

        return DB::transaction(function () use ($loan, $data, $balance) {
            // El nuevo préstamo nace con el saldo pendiente exacto del viejo:
            // no se recibe un monto en el formulario.
            $newLoan = $this->createLoan->execute($loan->client, [
                ...$data,
                'principal_amount' => (string) $balance,
                'refinanced_from_id' => $loan->id,
            ]);

            // Las cuotas ya pagadas quedan como historial; las que quedaban
            // por pagar se anulan y el préstamo pasa a estado refinanciado.
            $loan->installments()
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->update(['status' => 'cancelled']);

            $loan->update([
                'status' => LoanStatus::Refinanced,
                'pending_balance' => 0,
                'notes' => trim(($loan->notes ?? '')."\nRefinanciado en el préstamo #".$newLoan->id),
                'updated_by' => auth()->id(),
            ]);

            Event::create([
                'event_type' => EventType::LoanRefinanced,
                'client_id' => $loan->client_id,
                'loan_id' => $loan->id,
                'extra_data' => [
                    'new_loan_id' => $newLoan->id,
                    'balance' => $balance,
                    'new_installments' => $data['installments_count'],
                ],
                'created_at' => now(),
                'created_by' => auth()->id(),
            ]);

            $this->scoreService->update($loan->client);

            return $newLoan->fresh('installments');
        });
    }
}
