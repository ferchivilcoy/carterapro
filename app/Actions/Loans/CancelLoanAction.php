<?php

namespace App\Actions\Loans;

use App\Enums\EventType;
use App\Enums\LoanStatus;
use App\Models\Event;
use App\Models\Loan;
use App\Services\ClientScoreService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelLoanAction
{
    public function __construct(
        private readonly ClientScoreService $scoreService,
    ) {}

    public function execute(Loan $loan, string $reason): Loan
    {
        if ($loan->payments()->active()->exists()) {
            throw new RuntimeException('No se puede cancelar un préstamo con pagos registrados.');
        }

        return DB::transaction(function () use ($loan, $reason) {
            $loan->update([
                'status' => LoanStatus::Cancelled,
                'notes' => trim(($loan->notes ?? '')."\n[Cancelado] ".$reason),
                'updated_by' => auth()->id(),
            ]);

            $loan->installments()->update(['status' => 'cancelled']);

            Event::create([
                'event_type' => EventType::LoanCancelled,
                'client_id' => $loan->client_id,
                'loan_id' => $loan->id,
                'extra_data' => ['reason' => $reason],
                'created_at' => now(),
                'created_by' => auth()->id(),
            ]);

            $this->scoreService->update($loan->client);

            return $loan->fresh();
        });
    }
}
