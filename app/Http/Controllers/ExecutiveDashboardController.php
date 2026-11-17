<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Enums\Role;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExecutiveDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        [$fromCarbon, $toCarbon] = $this->period($from, $to);

        // ===== Cartera por estado (para gráfico doughnut) =====
        $loansByStatus = Loan::query()
            ->selectRaw('status, count(*) as loans, coalesce(sum(pending_balance), 0) as balance')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->status->value => [
                    'label' => $row->status->label(),
                    'loans' => (int) $row->loans,
                    'balance' => (float) $row->balance,
                    'color' => $row->status->color(),
                ],
            ]);

        // ===== Mora por rango de días (para gráfico de barras) =====
        $overdue = Installment::overdue()->with(['loan', 'loan.client'])->get();
        $buckets = [
            '1-30' => ['label' => '1 a 30 días', 'min' => 1, 'max' => 30, 'color' => '#fbbf24'],
            '31-60' => ['label' => '31 a 60 días', 'min' => 31, 'max' => 60, 'color' => '#f59e0b'],
            '61-90' => ['label' => '61 a 90 días', 'min' => 61, 'max' => 90, 'color' => '#ef4444'],
            '90+' => ['label' => 'Más de 90 días', 'min' => 91, 'max' => null, 'color' => '#dc2626'],
        ];

        $moraByRange = [];
        foreach ($buckets as $key => $bucket) {
            $group = $overdue->filter(fn ($i) => $this->inDaysRange($i->daysOverdue(), $bucket['min'], $bucket['max']));
            $moraByRange[$key] = [
                'label' => $bucket['label'],
                'count' => $group->count(),
                'amount' => (float) $group->sum('pending_balance'),
                'lateFees' => (float) $group->sum('late_fee'),
                'color' => $bucket['color'],
            ];
        }

        // ===== Cobranzas por cobrador (para gráfico de barras) =====
        $collectors = User::query()
            ->where('role', Role::Cobrador->value)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $collectorRows = [];
        foreach ($collectors as $collector) {
            $clientIds = Client::query()
                ->where('assigned_collector_id', $collector->id)
                ->pluck('id');

            $payments = Payment::active()
                ->whereIn('client_id', $clientIds)
                ->whereBetween('payment_date', [$fromCarbon, $toCarbon])
                ->get();

            // El monto del recibo incluye la mora: se separa para mostrar
            // capital cobrado y mora cobrada sin duplicar el total.
            $collected = (float) $payments->sum(fn ($payment) => (float) $payment->amount - (float) $payment->late_fee_amount);
            $collectedLateFees = (float) $payments->sum('late_fee_amount');

            $overdueCount = (int) Installment::overdue()
                ->whereHas('loan', fn ($query) => $query->whereIn('client_id', $clientIds))
                ->count();

            $collectorRows[] = [
                'name' => $collector->name,
                'clients' => (int) $clientIds->count(),
                'collected' => $collected,
                'collectedLateFees' => $collectedLateFees,
                'collectedTotal' => $collected + $collectedLateFees,
                'overdueCount' => $overdueCount,
            ];
        }

        usort($collectorRows, fn (array $a, array $b) => $b['collectedTotal'] <=> $a['collectedTotal']);

        // ===== Rentabilidad mensual (para gráfico de líneas) =====
        $monthlyProfitability = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $start = $month->copy()->startOfMonth()->toDateString();
            $end = $month->copy()->endOfMonth()->toDateString();

            $payments = Payment::active()->whereBetween('payment_date', [$start, $end])->get();
            $capitalCollected = (float) $payments->sum(fn ($p) => (float) $p->amount - (float) $p->late_fee_amount);
            $lateFeesCollected = (float) $payments->sum('late_fee_amount');

            $expenses = (float) CashMovement::query()
                ->where('type', 'expense')
                ->whereBetween('date', [$start, $end])
                ->sum('amount');

            $monthlyProfitability[] = [
                'month' => $month->format('M Y'),
                'key' => $month->format('Y-m'),
                'capitalCollected' => round($capitalCollected, 2),
                'lateFeesCollected' => round($lateFeesCollected, 2),
                'totalCollected' => round($capitalCollected + $lateFeesCollected, 2),
                'expenses' => round($expenses, 2),
                'netProfitability' => round($lateFeesCollected - $expenses, 2),
            ];
        }

        // ===== Métricas clave =====
        $totalLoans = (int) Loan::query()->count();
        $totalPrincipal = (float) Loan::query()->sum('principal_amount');
        $pendingBalance = (float) Loan::query()->sum('pending_balance');
        $activeLoans = (int) Loan::query()->whereIn('status', [LoanStatus::Active, LoanStatus::Overdue])->count();
        $overdueInstallmentsCount = (int) Installment::overdue()->count();
        $overdueInstallmentsAmount = (float) Installment::overdue()->sum('pending_balance');
        $lateFeesUncollected = (float) Installment::overdue()->sum('late_fee');

        $currentMonthStart = now()->startOfMonth()->toDateString();
        $currentMonthEnd = now()->endOfMonth()->toDateString();

        $currentPayments = Payment::active()->whereBetween('payment_date', [$currentMonthStart, $currentMonthEnd])->get();
        $capitalCollectedMTD = (float) $currentPayments->sum(fn ($p) => (float) $p->amount - (float) $p->late_fee_amount);
        $lateFeesCollectedMTD = (float) $currentPayments->sum('late_fee_amount');
        $totalCollectedMTD = $capitalCollectedMTD + $lateFeesCollectedMTD;

        $expensesMTD = (float) CashMovement::query()
            ->where('type', 'expense')
            ->whereBetween('date', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');

        $netProfitabilityMTD = $lateFeesCollectedMTD - $lateFeesUncollected - $expensesMTD;

        return view('reports.executive', [
            'from' => $fromCarbon->format('Y-m-d'),
            'to' => $toCarbon->format('Y-m-d'),
            'totalLoans' => $totalLoans,
            'totalPrincipal' => $totalPrincipal,
            'pendingBalance' => $pendingBalance,
            'activeLoans' => $activeLoans,
            'overdueInstallmentsCount' => $overdueInstallmentsCount,
            'overdueInstallmentsAmount' => $overdueInstallmentsAmount,
            'lateFeesUncollected' => $lateFeesUncollected,
            'capitalCollectedMTD' => $capitalCollectedMTD,
            'lateFeesCollectedMTD' => $lateFeesCollectedMTD,
            'totalCollectedMTD' => $totalCollectedMTD,
            'expensesMTD' => $expensesMTD,
            'netProfitabilityMTD' => $netProfitabilityMTD,
            'loansByStatus' => $loansByStatus,
            'moraByRange' => $moraByRange,
            'collectorRows' => $collectorRows,
            'monthlyProfitability' => $monthlyProfitability,
        ]);
    }

    /**
     * Rango de fechas con sensato por defecto: mes en curso hasta hoy.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(string $from = '', string $to = ''): array
    {
        $fromCarbon = $from !== '' ? Carbon::parse($from) : now()->startOfMonth();
        $toCarbon = $to !== '' ? Carbon::parse($to) : now();

        return [$fromCarbon->startOfDay(), $toCarbon->endOfDay()];
    }

    /**
     * Un valor cae en el rango [min, max]; max null significa "abierto".
     */
    private function inDaysRange(int $value, ?int $min, ?int $max): bool
    {
        if ($min !== null && $value < $min) {
            return false;
        }

        return $max === null || $value <= $max;
    }
}
