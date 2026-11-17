<?php

namespace App\Http\Controllers;

use App\Models\Installment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstallmentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status', 'all');
        $period = $request->string('period', 'all');

        $installments = Installment::query()
            ->with('loan.client')
            ->filtered($request->string('search')->toString(), $status->toString(), $period->toString())
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->paginate(15)
            ->withQueryString();

        // All three filters are normalized to plain strings, because the views
        // compare them against '' and 'all' to decide what to reset and which
        // empty state to show.
        return view('installments.index', [
            'installments' => $installments,
            'search' => $request->string('search', '')->toString(),
            'status' => $status->toString(),
            'period' => $period->toString(),
        ]);
    }
}
