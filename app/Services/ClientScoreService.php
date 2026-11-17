<?php

namespace App\Services;

use App\Enums\ClientTier;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Computes the credit score of a client (0-100) from its payment history and
 * stores it together with the matching level.
 *
 * The score is a weighted sum of up to four signals, whose weights live in
 * the settings table and must add to 100:
 * - share of evaluated installments already paid (score_weight_paid)
 * - absence of overdue installments among the evaluated ones (score_weight_overdue)
 * - share of closed (paid) loans over the evaluated loans (score_weight_loans)
 * - share of evaluated installments paid without late fee (score_weight_ontime)
 *
 * Only paid installments and installments whose due date has already passed
 * are evaluated: a future installment says nothing about the behaviour yet, so
 * a brand new loan does not move the score on its own. A client without any
 * evaluated installment stays at 0 (Inicial).
 */
class ClientScoreService
{
    public function update(Client $client): void
    {
        $score = $this->compute($client);

        $client->update([
            'current_score' => $score,
            'tier' => ClientTier::fromScore($score),
        ]);
    }

    public function compute(Client $client): int
    {
        $weights = Setting::scoreWeights();

        $installments = Installment::query()
            ->whereHas('loan', fn ($query) => $query->where('client_id', $client->id))
            ->where('status', '!=', InstallmentStatus::Cancelled)
            ->with('loan')
            ->get();

        $evaluated = $this->evaluated($installments);

        if ($evaluated->isEmpty()) {
            return 0;
        }

        $paid = $evaluated->filter(fn (Installment $installment) => $installment->status === InstallmentStatus::Paid);
        $overdue = $evaluated->filter(fn (Installment $installment) => $installment->status === InstallmentStatus::Overdue);
        $ontime = $paid->filter(fn (Installment $installment) => (float) $installment->late_fee === 0);

        $loans = $client->loans()
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Paid, LoanStatus::Overdue])
            ->get();
        $closedLoans = $loans->filter(fn ($loan) => $loan->status === LoanStatus::Paid);

        $paidRatio = $paid->count() / $evaluated->count();
        $overdueRatio = 1 - ($overdue->count() / $evaluated->count());
        $loanRatio = $loans->isEmpty() ? 0 : $closedLoans->count() / $loans->count();
        $ontimeRatio = $ontime->count() / $evaluated->count();

        return (int) round(100 * (
            $weights['paid'] * $paidRatio
            + $weights['overdue'] * $overdueRatio
            + $weights['loans'] * $loanRatio
            + $weights['ontime'] * $ontimeRatio
        ));
    }

    /**
     * Installments that already say something about the behaviour: the paid
     * ones (even when due later, they were settled early) and the ones whose
     * due date has arrived. Cancelled installments are excluded upstream.
     *
     * @param  Collection<int, Installment>  $installments
     * @return Collection<int, Installment>
     */
    private function evaluated(Collection $installments): Collection
    {
        return $installments->filter(
            fn (Installment $installment) => $installment->status === InstallmentStatus::Paid
                || $installment->due_date->lte(today())
        )->values();
    }
}
