<?php

namespace App\Http\Controllers\Api;

use App\Models\Installment;
use Illuminate\Http\Request;

class InstallmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Installment::query()->with(['loan.client']);

        if ($search = $request->string('search')) {
            $query->whereHas('loan.client', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->string('from')) {
            $query->whereDate('due_date', '>=', $from);
        }

        if ($to = $request->string('to')) {
            $query->whereDate('due_date', '<=', $to);
        }

        $installments = $query->orderBy('due_date')->paginate(15)->withQueryString();

        return $this->success($installments, 'Cuotas listadas');
    }

    public function show(Installment $installment)
    {
        $installment->load(['loan.client', 'payments']);

        return $this->success($installment, 'Cuota encontrada');
    }

    public function dueToday(Request $request)
    {
        $installments = Installment::query()
            ->with(['loan.client'])
            ->whereDate('due_date', today())
            ->where('status', '!=', 'pagada')
            ->orderBy('due_date')
            ->get();

        return $this->success($installments, 'Cuotas vencidas hoy');
    }
}
