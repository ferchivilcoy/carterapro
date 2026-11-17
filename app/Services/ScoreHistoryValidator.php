<?php

namespace App\Services;

use App\Enums\ClientTier;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Models\Client;
use App\Models\Loan;
use Illuminate\Support\Facades\DB;

/**
 * Valida el scoring interno contra el historial real: agrupa a los clientes
 * por nivel (tier) del score almacenado y mide la tasa de clientes que
 * alguna vez incumplieron (préstamo vencido o cuota vencida). Un scoring
 * válido muestra tasas de incumplimiento decrecientes a medida que sube el
 * nivel, lo que confirma que los pesos configurados predicen el riesgo.
 */
class ScoreHistoryValidator
{
    /**
     * Resultado por nivel de score.
     *
     * @return array<int, array{tier: string, clients: int, defaulted: int, default_rate: float, avg_score: float}>
     */
    public function validate(): array
    {
        $clients = Client::query()
            ->with(['loans.installments'])
            ->get();

        // Pre-load all overdue loans and installments to avoid N+1
        $clientIds = $clients->pluck('id')->toArray();

        $overdueLoanClientIds = Loan::query()
            ->whereIn('client_id', $clientIds)
            ->where('status', LoanStatus::Overdue)
            ->pluck('client_id')
            ->unique()
            ->toArray();

        $overdueInstallmentClientIds = DB::table('installments')
            ->join('loans', 'installments.loan_id', '=', 'loans.id')
            ->whereIn('loans.client_id', $clientIds)
            ->where('installments.status', InstallmentStatus::Overdue->value)
            ->pluck('loans.client_id')
            ->unique()
            ->toArray();

        $defaultedClientIds = array_unique(array_merge($overdueLoanClientIds, $overdueInstallmentClientIds));

        $buckets = [];

        foreach ($clients as $client) {
            $tier = ClientTier::fromScore((int) $client->current_score);
            $key = $tier->value;

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'tier' => $tier->label(),
                    'clients' => 0,
                    'defaulted' => 0,
                    'score_sum' => 0,
                ];
            }

            $bucket = &$buckets[$key];
            $bucket['clients']++;
            $bucket['score_sum'] += (int) $client->current_score;

            if (in_array($client->id, $defaultedClientIds, true)) {
                $bucket['defaulted']++;
            }
        }

        $result = [];
        foreach (ClientTier::cases() as $tier) {
            $key = $tier->value;
            $bucket = $buckets[$key] ?? null;

            if (! $bucket) {
                continue;
            }

            $clientsCount = (int) $bucket['clients'];
            $defaulted = (int) $bucket['defaulted'];

            $result[] = [
                'tier' => $bucket['tier'],
                'clients' => $clientsCount,
                'defaulted' => $defaulted,
                'default_rate' => $clientsCount > 0 ? round(100 * $defaulted / $clientsCount, 2) : 0,
                'avg_score' => $clientsCount > 0 ? round($bucket['score_sum'] / $clientsCount, 1) : 0,
            ];
        }

        return $result;
    }
}
