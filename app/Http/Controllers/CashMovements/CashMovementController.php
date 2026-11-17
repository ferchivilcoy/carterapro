<?php

namespace App\Http\Controllers\CashMovements;

use App\Enums\CashType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashMovements\IndexCashMovementRequest;
use App\Http\Requests\CashMovements\StoreCashMovementRequest;
use App\Models\CashMovement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CashMovementController extends Controller
{
    public function index(IndexCashMovementRequest $request): View
    {
        $validated = $request->validated();
        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $query = CashMovement::query()->with('user')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to);

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $movements = $query->orderByDesc('date')->orderByDesc('id')->paginate(20)->withQueryString();

        $totalIncomes = (float) $movements->getCollection()->where('type', 'income')->sum('amount');
        $totalExpenses = (float) $movements->getCollection()->where('type', 'expense')->sum('amount');
        $open = CashMovement::open()->count();

        return view('cash-movements.index', [
            'movements' => $movements,
            'from' => $from,
            'to' => $to,
            'type' => $validated['type'] ?? 'all',
            'status' => $validated['status'] ?? 'all',
            'totalIncomes' => $totalIncomes,
            'totalExpenses' => $totalExpenses,
            'balance' => $totalIncomes - $totalExpenses,
            'open' => $open,
            'types' => CashType::cases(),
            'users' => User::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCashMovementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $data['status'] = 'open';

        CashMovement::create($data);

        return redirect()
            ->route('cash-movements.index')
            ->with('cash_success', 'Cash movement registered successfully.');
    }

    public function close(): RedirectResponse
    {
        $open = CashMovement::open()->get();

        foreach ($open as $movement) {
            $movement->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);
        }

        return redirect()
            ->route('cash-movements.index')
            ->with('cash_success', 'Closed '.$open->count().' open movement(s).');
    }
}
