<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    /**
     * Estado de la cartera: cuántos préstamos hay, cuánto se debe, cómo se
     * reparte por estado y nivel de riesgo, y los clientes con más saldo.
     */
    public function cartera(Request $request): View
    {
        $status = $request->string('status', 'all')->toString();
        $search = $request->string('search')->toString();

        $loans = Loan::query()->with(['client']);

        $loans = $this->filterLoans($loans, $search, $status);

        $totalLoans = (int) $loans->count();
        $totalPrincipal = (float) $loans->clone()->sum('principal_amount');
        $pendingBalance = (float) $loans->clone()->sum('pending_balance');

        $byStatus = $loans->clone()
            ->selectRaw('status, count(*) as loans, coalesce(sum(pending_balance), 0) as balance')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->status->value => [
                    'loans' => (int) $row->loans,
                    'balance' => (float) $row->balance,
                ],
            ]);

        $overdueInstallments = Installment::overdue()
            ->with(['loan', 'loan.client'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $topDebt = Loan::query()
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Overdue])
            ->with('client')
            ->select('client_id', DB::raw('SUM(pending_balance) as debt'), DB::raw('count(*) as loans'))
            ->groupBy('client_id')
            ->orderByDesc('debt')
            ->limit(8)
            ->get();

        return view('reports.portfolio', [
            'status' => $status,
            'search' => $search,
            'totalLoans' => $totalLoans,
            'totalPrincipal' => $totalPrincipal,
            'pendingBalance' => $pendingBalance,
            'byStatus' => $byStatus,
            'overdueInstallmentsCount' => (int) Installment::overdue()->count(),
            'overdueInstallmentsAmount' => (float) Installment::overdue()->sum('pending_balance'),
            'overdueInstallments' => $overdueInstallments,
            'topDebt' => $topDebt,
        ]);
    }

    /**
     * Mora: cuotas vencidas, importe vencido, mora devengada y distribución
     * por rangos de días de atraso.
     */
    public function mora(Request $request): View
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $query = Installment::overdue()->with(['loan', 'loan.client']);

        if ($from !== '') {
            $query->whereDate('due_date', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('due_date', '<=', $to);
        }

        $installments = $query->orderBy('due_date')->get();

        $count = $installments->count();
        $amount = (float) $installments->sum('pending_balance');
        $lateFees = (float) $installments->sum('late_fee');

        $buckets = [
            '1-30' => ['label' => '1 a 30 días', 'min' => 1, 'max' => 30],
            '31-60' => ['label' => '31 a 60 días', 'min' => 31, 'max' => 60],
            '61-90' => ['label' => '61 a 90 días', 'min' => 61, 'max' => 90],
            '90+' => ['label' => 'Más de 90 días', 'min' => 91, 'max' => null],
        ];

        $distribution = [];
        foreach ($buckets as $key => $bucket) {
            $group = $installments->filter(fn ($i) => $this->inDaysRange($i->daysOverdue(), $bucket['min'], $bucket['max']));
            $distribution[$key] = [
                'label' => $bucket['label'],
                'count' => $group->count(),
                'amount' => (float) $group->sum('pending_balance'),
                'items' => $group,
            ];
        }

        return view('reports.delinquency', [
            'from' => $from,
            'to' => $to,
            'count' => $count,
            'amount' => $amount,
            'lateFees' => $lateFees,
            'distribution' => $distribution,
            'installments' => $installments,
        ]);
    }

    /**
     * Caja diaria: cobros efectivos del periodo, desglose por medio de pago y
     * por día.
     */
    public function cajaDiaria(Request $request): View
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $method = $request->string('method', 'all')->toString();

        [$fromCarbon, $toCarbon] = $this->period($from, $to);

        $query = Payment::active()->whereBetween('payment_date', [$fromCarbon, $toCarbon]);

        if ($method !== 'all' && in_array($method, array_map(fn ($case) => $case->value, PaymentMethod::cases()), true)) {
            $query->where('method', $method);
        }

        $payments = $query->orderBy('payment_date')->orderByDesc('id')->get();

        $count = $payments->count();
        // Payment::amount already includes the late fee, so the period total is
        // the sum of the receipts and the late fee is only an informative slice.
        $amount = (float) $payments->sum('amount');
        $lateFees = (float) $payments->sum('late_fee_amount');
        $total = $amount;

        $byMethod = $payments->groupBy(fn ($p) => $p->method->value)
            ->map(fn ($group, $methodKey) => [
                'label' => PaymentMethod::from($methodKey)->label(),
                'count' => $group->count(),
                'amount' => (float) $group->sum('amount'),
            ])->values()->all();

        $byDay = $payments->groupBy(fn ($p) => $p->payment_date->toDateString())
            ->map(fn ($group, $day) => [
                'day' => Carbon::parse($day)->format('d/m/Y'),
                'count' => $group->count(),
                'amount' => (float) $group->sum('amount'),
                'lateFees' => (float) $group->sum('late_fee_amount'),
            ])->values()->all();

        return view('reports.daily-cash', [
            'from' => $fromCarbon->format('Y-m-d'),
            'to' => $toCarbon->format('Y-m-d'),
            'method' => $method,
            'methods' => PaymentMethod::cases(),
            'count' => $count,
            'amount' => $amount,
            'lateFees' => $lateFees,
            'total' => $total,
            'byMethod' => $byMethod,
            'byDay' => $byDay,
            'payments' => $payments,
        ]);
    }

    /**
     * Cobranzas por cobrador: clientes, cartera, cobrados y vencidas a nombre
     * de cada cobrador.
     */
    public function cobranzasCobrador(Request $request): View
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        [$fromCarbon, $toCarbon] = $this->period($from, $to);

        $collectors = User::query()
            ->where('role', Role::Cobrador->value)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Un cobrador solo ve su propia fila; el resto de roles ven todos.
        $viewer = auth()->user();
        if ($viewer?->isCollector()) {
            $collectors = $collectors->where('id', $viewer->id);
        }

        $rows = [];
        foreach ($collectors as $collector) {
            $clientIds = Client::query()
                ->where('assigned_collector_id', $collector->id)
                ->pluck('id');

            $clientsCount = $clientIds->count();

            $loans = Loan::query()->whereIn('client_id', $clientIds);
            $activeLoans = (int) $loans->where('status', LoanStatus::Active)->count();
            $pendingBalance = (float) $loans->clone()->sum('pending_balance');

            $payments = Payment::active()
                ->whereIn('client_id', $clientIds)
                ->whereBetween('payment_date', [$fromCarbon, $toCarbon])
                ->get();

            // El monto del recibo incluye la mora: se separa para mostrar
            // capital cobrado y mora cobrada sin sumar dos veces lo mismo.
            $collected = (float) $payments->sum(fn ($payment) => (float) $payment->amount - (float) $payment->late_fee_amount);
            $collectedLateFees = (float) $payments->sum('late_fee_amount');

            $overdueCount = (int) Installment::overdue()
                ->whereHas('loan', fn ($query) => $query->whereIn('client_id', $clientIds))
                ->count();

            $rows[] = [
                'collector' => $collector,
                'clients' => $clientsCount,
                'activeLoans' => $activeLoans,
                'pendingBalance' => $pendingBalance,
                'collected' => $collected,
                'collectedLateFees' => $collectedLateFees,
                'collectedTotal' => $collected + $collectedLateFees,
                'overdueCount' => $overdueCount,
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['collectedTotal'] <=> $a['collectedTotal']);

        return view('reports.collections-collector', [
            'from' => $fromCarbon->format('Y-m-d'),
            'to' => $toCarbon->format('Y-m-d'),
            'rows' => $rows,
            'totalClients' => array_sum(array_column($rows, 'clients')),
            'totalActiveLoans' => array_sum(array_column($rows, 'activeLoans')),
            'totalPending' => array_sum(array_column($rows, 'pendingBalance')),
            'totalCollected' => array_sum(array_column($rows, 'collected')),
            'totalLateFees' => array_sum(array_column($rows, 'collectedLateFees')),
            'totalOverdue' => array_sum(array_column($rows, 'overdueCount')),
        ]);
    }

    /**
     * Filtros comunes del listado de préstamos de cartera.
     */
    private function filterLoans(Builder $query, string $search, string $status)
    {
        return $query
            ->when($status !== 'all' && in_array($status, array_map(fn ($case) => $case->value, LoanStatus::cases()), true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereHas('client', fn ($client) => $client->search($search)))
            ->latest('created_at')
            ->orderByDesc('id');
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
