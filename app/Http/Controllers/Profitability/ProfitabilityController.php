<?php

namespace App\Http\Controllers\Profitability;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use Illuminate\View\View;

class ProfitabilityController extends Controller
{
    public function index(): View
    {
        $startMonth = now()->startOfMonth()->toDateString();
        $endMonth = now()->endOfMonth()->toDateString();

        $payments = Payment::active()->whereBetween('payment_date', [$startMonth, $endMonth])->get();

        $capitalCollected = (float) $payments->sum(fn ($p) => (float) $p->amount - (float) $p->late_fee_amount);
        $lateFeesCollected = (float) $payments->sum('late_fee_amount');
        $totalCollected = $capitalCollected + $lateFeesCollected;

        $activeLoans = Loan::query()->whereIn('status', ['active', 'overdue'])->get();
        $pendingBalance = (float) $activeLoans->sum('pending_balance');
        $totalPrincipal = (float) $activeLoans->sum('principal_amount');

        $overdueInstallments = Installment::overdue()->get();
        $lateFeesUncollected = (float) $overdueInstallments->sum('late_fee');
        $overdueBalance = (float) $overdueInstallments->sum('pending_balance');

        $expensesThisMonth = (float) CashMovement::query()
            ->where('type', 'expense')
            ->whereBetween('date', [$startMonth, $endMonth])
            ->sum('amount');

        $netProfitability = $lateFeesCollected - $lateFeesUncollected - $expensesThisMonth;

        return view('profitability.index', [
            'capitalCollected' => $capitalCollected,
            'lateFeesCollected' => $lateFeesCollected,
            'totalCollected' => $totalCollected,
            'pendingBalance' => $pendingBalance,
            'totalPrincipal' => $totalPrincipal,
            'lateFeesUncollected' => $lateFeesUncollected,
            'overdueBalance' => $overdueBalance,
            'expensesThisMonth' => $expensesThisMonth,
            'netProfitability' => $netProfitability,
            'activeLoansCount' => $activeLoans->count(),
            'overdueCount' => $overdueInstallments->count(),
        ]);
    }
}
