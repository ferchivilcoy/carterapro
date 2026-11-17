<?php

namespace App\Services;

use App\Enums\EventType;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Models\Client;
use App\Models\Event;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moves the installments whose due date has already passed into the overdue
 * status and flags the loans that carry them. It also refreshes the late fee
 * of every overdue installment, so the accrued amount follows the calendar.
 *
 * The overdue status is stored rather than derived from the due date on read,
 * so this service is what keeps that stored state in sync with the calendar.
 * It runs from the nightly schedule and, as a safety net for the environments
 * where no scheduler is running, from the first web request of the day.
 */
class OverdueInstallmentsService
{
    /**
     * Marker holding the day the check last completed on. Keyed by date, so a
     * new day always triggers a run while a second pass on the same day does
     * not.
     */
    private const CACHE_KEY = 'loans.overdue-checked-on';

    public function __construct(
        private readonly ClientScoreService $scoreService,
        private readonly LateFeeService $lateFee,
    ) {}

    public function graceDays(): int
    {
        return Setting::getInt('overdue_grace_days', Setting::DEFAULTS['overdue_grace_days']);
    }

    /**
     * Runs the sweep at most once per calendar day.
     *
     * The marker is written only after a successful run, so a sweep that threw
     * is retried by the next caller instead of being silently skipped for the
     * rest of the day.
     */
    public function refreshOncePerDay(): void
    {
        $today = today()->toDateString();

        if (cache()->get(self::CACHE_KEY) === $today) {
            return;
        }

        $this->run();

        cache()->forever(self::CACHE_KEY, $today);
    }

    /**
     * Ids of the installments that passed their due date and are still payable.
     *
     * Only pending and partial rows are candidates. Leaving overdue out of the
     * set is what makes a second run a no-op instead of duplicating the audit
     * trail, and the bound is strictly less than the cutoff so an installment
     * due today is still "due today" and only becomes overdue once the day is
     * over.
     *
     * @return Collection<int, int>
     */
    public function candidates(): Collection
    {
        return Installment::query()
            ->whereIn('status', [InstallmentStatus::Pending, InstallmentStatus::Partial])
            ->whereDate('due_date', '<', today()->subDays($this->graceDays()))
            ->orderBy('id')
            ->pluck('id');
    }

    /**
     * Flags every candidate and returns how many rows changed.
     *
     * @param  Collection<int, int>  $installmentIds
     * @return array{installments: int, loans: int}
     */
    public function flag(Collection $installmentIds, ?int $userId = null): array
    {
        $flagged = 0;
        $userId ??= auth()->id();

        // Chunked so a large backlog neither exhausts the memory nor holds a
        // single transaction open across the whole table.
        foreach ($installmentIds->chunk(200) as $chunk) {
            DB::transaction(function () use ($chunk, &$flagged, $userId) {
                $installments = Installment::query()
                    ->whereIn('id', $chunk)
                    ->with('loan')
                    ->get();

                foreach ($installments as $installment) {
                    $installment->update(['status' => InstallmentStatus::Overdue]);

                    Event::create([
                        'event_type' => EventType::InstallmentOverdue,
                        'client_id' => $installment->loan?->client_id,
                        'loan_id' => $installment->loan_id,
                        'installment_id' => $installment->id,
                        'extra_data' => [
                            'due_date' => $installment->due_date?->toDateString(),
                            'amount' => $installment->pending_balance,
                        ],
                        'created_at' => now(),
                        'created_by' => $userId,
                    ]);

                    $flagged++;
                }
            });
        }

        // A new overdue installment changes the score of every client that
        // owns one, so their stored score and level are refreshed here too.
        $this->updateClientScores($installmentIds);

        return [
            'installments' => $flagged,
            'loans' => $this->flagLoans($installmentIds),
        ];
    }

    /**
     * Recomputes the score of the clients behind the given installments.
     *
     * @param  Collection<int, int>  $installmentIds
     */
    private function updateClientScores(Collection $installmentIds): void
    {
        if ($installmentIds->isEmpty()) {
            return;
        }

        $loanIds = Installment::query()
            ->whereIn('id', $installmentIds)
            ->distinct()
            ->pluck('loan_id');

        $clientIds = Loan::query()
            ->whereIn('id', $loanIds)
            ->distinct()
            ->pluck('client_id');

        Client::query()
            ->whereIn('id', $clientIds)
            ->get()
            ->each(fn (Client $client) => $this->scoreService->update($client));
    }

    /**
     * @return array{installments: int, loans: int}
     */
    public function run(?int $userId = null): array
    {
        $result = $this->flag($this->candidates(), $userId);

        $this->refreshFees($userId);

        return $result;
    }

    /**
     * Recalcula la mora de todas las cuotas vencidas con saldo pendiente.
     *
     * Se expone como paso aparte porque el comando nocturno debe poder
     * ejecutarlo aunque no haya cuotas nuevas que marcar: el interés sigue
     * devengándose cada día que pasa.
     *
     * @return int Filas revisadas
     */
    public function refreshFees(?int $userId = null): int
    {
        return $this->lateFee->refreshOverdue();
    }

    /**
     * Loans that own at least one of the given installments, without writing.
     *
     * @param  Collection<int, int>  $installmentIds
     */
    public function loansWithCandidates(Collection $installmentIds): int
    {
        return Installment::query()
            ->whereIn('id', $installmentIds)
            ->distinct()
            ->count('loan_id');
    }

    /**
     * @param  Collection<int, int>  $installmentIds
     */
    private function flagLoans(Collection $installmentIds): int
    {
        $loanIds = Installment::query()
            ->whereIn('id', $installmentIds)
            ->distinct()
            ->pluck('loan_id');

        // A paid or cancelled loan keeps its final status even if it still owns
        // a stale overdue installment.
        return Loan::query()
            ->whereIn('id', $loanIds)
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Overdue])
            ->update(['status' => LoanStatus::Overdue]);
    }
}
