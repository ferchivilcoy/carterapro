<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Enums\Module;
use App\Models\CollectionVisit;
use App\Models\Event;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\RefinanceRequest;
use App\Models\Setting;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ModuleManager $modules,
    ) {}

    public function index(): View
    {
        $today = today()->toDateString();
        $startMonth = now()->startOfMonth()->toDateString();
        $endMonth = now()->endOfMonth()->toDateString();
        $last30Start = now()->subDays(30)->toDateString();
        $previousMonthStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $previousMonthEnd = now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $enabledModules = $this->modules->statesForRole(auth()->user()?->role);
        $isEnabled = fn (Module $module): bool => $enabledModules[$module->value];

        // La vista recibe siempre las mismas variables, aunque el módulo esté
        // apagado. Cada bloque completa sólo los datos que le corresponden y
        // deja los valores neutros para que ninguna tarjeta se rompa.
        $monthIncome = 0.0;
        $monthPaymentsCount = 0;
        $incomeToday = 0.0;
        $paymentsTodayCount = 0;
        $incomeYesterday = 0.0;
        $previousMonthIncome = 0.0;
        $incomeLast30 = 0.0;
        $monthIncomeChange = null;
        $incomeTodayChange = null;

        $pendingBalance = 0.0;
        $activeLoansCount = 0;
        $paidLoansCount = 0;
        $cancelledLoansCount = 0;

        $overdueCount = 0;
        $overdueAmount = 0.0;
        $overdueLateFees = 0.0;
        $overdueTotal = 0.0;
        $dueToday = collect();
        $dueTodayTotal = 0.0;
        $overdue = collect();
        $upcoming = collect();
        $upcomingTotal = 0.0;

        $reminderDays = 0;
        $reminders = collect();
        $remindersCount = 0;
        $remindersTotal = 0.0;

        $eventsRecent = collect();
        $pendingRefinanceCount = 0;
        $pendingRefinanceRequests = collect();
        $pendingContact = 0;

        $loansByStatus = [];
        $loanStatusRows = [];
        $totalLoans = 0;
        $topClientsDebt = collect();

        $incomeSeries = ['items' => [], 'total' => 0.0, 'average' => 0.0];

        if ($isEnabled(Module::Payments)) {
            $monthPayments = Payment::active()
                ->whereBetween('payment_date', [$startMonth, $endMonth]);
            $monthIncome = (float) $monthPayments->clone()->sum('amount');
            $monthPaymentsCount = (int) $monthPayments->clone()->count();

            $todayPayments = Payment::active()
                ->whereDate('payment_date', $today);
            $incomeToday = (float) $todayPayments->clone()->sum('amount');
            $paymentsTodayCount = (int) $todayPayments->clone()->count();

            $incomeYesterday = (float) Payment::active()
                ->whereDate('payment_date', today()->subDay()->toDateString())
                ->sum('amount');

            $previousMonthIncome = (float) Payment::active()
                ->whereBetween('payment_date', [$previousMonthStart, $previousMonthEnd])
                ->sum('amount');

            $incomeLast30 = (float) Payment::active()
                ->whereBetween('payment_date', [$last30Start, $today])
                ->sum('amount');

            // Sin una base de comparación el porcentaje no significa nada, así
            // que el panel muestra la variación sólo cuando el período anterior
            // tuvo ingresos. El mismo criterio se usa para el día de hoy.
            $monthIncomeChange = $previousMonthIncome > 0
                ? (int) round((($monthIncome - $previousMonthIncome) / $previousMonthIncome) * 100)
                : null;

            $incomeTodayChange = $incomeYesterday > 0
                ? (int) round((($incomeToday - $incomeYesterday) / $incomeYesterday) * 100)
                : null;

            $incomeSeries = $this->incomeSeries();
        }

        if ($isEnabled(Module::Loans)) {
            $loansActiveOverdue = Loan::query()
                ->whereIn('status', [LoanStatus::Active, LoanStatus::Overdue]);

            $pendingBalance = (float) $loansActiveOverdue->clone()->sum('pending_balance');
            $activeLoansCount = (int) $loansActiveOverdue->clone()->count();

            $paidLoansCount = Loan::where('status', LoanStatus::Paid)->count();
            $cancelledLoansCount = Loan::where('status', LoanStatus::Cancelled)->count();

            $loansByStatus = Loan::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn ($v) => (int) $v)
                ->toArray();

            $totalLoans = array_sum($loansByStatus);

            // El panel usa los colores de Tabler (danger/secondary), no los del
            // enum (error/neutral), para que las clases de Bootstrap existan.
            $statusOrder = ['active', 'overdue', 'paid', 'refinanced', 'cancelled'];
            $loanStatusRows = collect($loansByStatus)
                ->sortBy(function (int $count, string $status) use ($statusOrder): int {
                    $position = array_search($status, $statusOrder, true);

                    return $position === false ? 99 : $position;
                })
                ->map(function (int $count, string $status) use ($totalLoans): array {
                    $case = LoanStatus::tryFrom($status);
                    $color = $case?->color() ?? 'neutral';
                    $color = ['error' => 'danger', 'neutral' => 'secondary'][$color] ?? $color;

                    return [
                        'label' => $case?->label() ?? ucfirst($status),
                        'count' => $count,
                        'color' => $color,
                        'percent' => $totalLoans > 0 ? (int) round(($count / $totalLoans) * 100) : 0,
                    ];
                })
                ->values()
                ->all();

            $topClientsDebt = Loan::query()
                ->whereIn('status', [LoanStatus::Active, LoanStatus::Overdue])
                ->with([
                    'client' => fn ($query) => $query->withCount([
                        'loans as active_loans_count' => fn ($loans) => $loans->where('status', LoanStatus::Active),
                    ]),
                ])
                ->select('client_id', DB::raw('SUM(pending_balance) as debt'))
                ->groupBy('client_id')
                ->orderByDesc('debt')
                ->limit(5)
                ->get();

            // Solicitudes de refinanciación que los clientes levantaron desde
            // su portal y que todavía nadie marcó como atendidas.
            $pendingRefinanceQuery = RefinanceRequest::query()->pending();
            $pendingRefinanceCount = (int) $pendingRefinanceQuery->clone()->count();
            $pendingRefinanceRequests = $pendingRefinanceQuery
                ->with(['client', 'loan'])
                ->latest('created_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        if ($isEnabled(Module::Installments)) {
            $overdueQuery = Installment::overdue();
            $overdueCount = (int) $overdueQuery->clone()->count();
            $overdueAmount = (float) $overdueQuery->clone()->sum('pending_balance');
            $overdueLateFees = (float) $overdueQuery->clone()->sum('late_fee');
            $overdueTotal = $overdueAmount + $overdueLateFees;

            $dueToday = Installment::dueToday()
                ->with(['loan', 'loan.client'])
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->get();
            $dueTodayTotal = (float) $dueToday->sum('pending_balance');

            $overdue = Installment::overdue()
                ->with(['loan', 'loan.client'])
                ->orderBy('due_date')
                ->limit(10)
                ->get();

            $upcoming = Installment::upcoming(7)
                ->with(['loan', 'loan.client'])
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->get();
            $upcomingTotal = (float) $upcoming->sum('pending_balance');

            // La ventana de recordatorio se consulta en el momento, así el panel
            // muestra lo mismo con o sin scheduler corriendo.
            $reminderDays = Setting::getInt('reminder_days_before', Setting::DEFAULTS['reminder_days_before']);

            if ($reminderDays > 0) {
                $remindersQuery = Installment::collectable()
                    ->whereDate('due_date', '<=', today()->addDays($reminderDays));

                $remindersCount = (int) $remindersQuery->clone()->count();
                $remindersTotal = (float) $remindersQuery->clone()->sum('pending_balance');

                $reminders = $remindersQuery
                    ->with(['loan', 'loan.client'])
                    ->orderBy('due_date')
                    ->orderBy('installment_number')
                    ->limit(15)
                    ->get();
            }
        }

        if ($isEnabled(Module::Events)) {
            $eventsRecent = Event::query()
                ->with(['client', 'loan'])
                ->latest('created_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        if ($isEnabled(Module::CollectionVisits)) {
            $pendingContact = CollectionVisit::pendingContact()->count();
        }

        return view('index', [
            'monthIncome' => $monthIncome,
            'monthPaymentsCount' => $monthPaymentsCount,
            'incomeToday' => $incomeToday,
            'paymentsTodayCount' => $paymentsTodayCount,
            'incomeYesterday' => $incomeYesterday,
            'incomeLast30' => $incomeLast30,
            'previousMonthIncome' => $previousMonthIncome,
            'monthIncomeChange' => $monthIncomeChange,
            'incomeTodayChange' => $incomeTodayChange,
            'pendingBalance' => $pendingBalance,
            'activeLoansCount' => $activeLoansCount,
            'paidLoansCount' => $paidLoansCount,
            'cancelledLoansCount' => $cancelledLoansCount,
            'overdueInstallmentsCount' => $overdueCount,
            'overdueAmount' => $overdueAmount,
            'overdueLateFees' => $overdueLateFees,
            'overdueTotal' => $overdueTotal,
            'dueToday' => $dueToday,
            'dueTodayTotal' => $dueTodayTotal,
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'upcomingTotal' => $upcomingTotal,
            'reminderDays' => $reminderDays,
            'reminders' => $reminders,
            'remindersCount' => $remindersCount,
            'remindersTotal' => $remindersTotal,
            'eventsRecent' => $eventsRecent,
            'loansByStatus' => $loansByStatus,
            'loanStatusRows' => $loanStatusRows,
            'totalLoans' => $totalLoans,
            'topClientsDebt' => $topClientsDebt,
            'pendingRefinanceRequests' => $pendingRefinanceRequests,
            'pendingRefinanceCount' => $pendingRefinanceCount,
            'pendingContact' => $pendingContact,
            'incomeSeries' => $incomeSeries['items'],
            'incomeSixMonthsTotal' => $incomeSeries['total'],
            'incomeMonthlyAverage' => $incomeSeries['average'],
            'enabledModules' => $enabledModules,
        ]);
    }

    /**
     * Serie de los últimos seis meses para el gráfico de ingresos del panel.
     *
     * Se resuelve en PHP para no depender de funciones de fecha propias de
     * SQLite o MySQL: el sistema puede correr sobre cualquiera de las dos.
     *
     * @return array{items: array<int, array{label: string, amount: float, percent: int, is_current: bool}>, total: float, average: float}
     */
    private function incomeSeries(): array
    {
        $monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        $items = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($monthNames): array {
                $month = now()->startOfMonth()->subMonths($monthsAgo);

                $amount = (float) Payment::active()
                    ->whereBetween('payment_date', [
                        $month->copy()->startOfMonth()->toDateString(),
                        $month->copy()->endOfMonth()->toDateString(),
                    ])
                    ->sum('amount');

                return [
                    'label' => $monthNames[(int) $month->format('n') - 1],
                    'amount' => $amount,
                    'is_current' => $monthsAgo === 0,
                ];
            });

        $maxAmount = max(1.0, (float) $items->max('amount'));
        $total = (float) $items->sum('amount');

        return [
            'items' => $items
                ->map(function (array $month) use ($maxAmount): array {
                    $month['percent'] = (int) round(($month['amount'] / $maxAmount) * 100);

                    return $month;
                })
                ->values()
                ->all(),
            'total' => $total,
            'average' => $total / 6,
        ];
    }
}
