<?php

namespace App\Http\Controllers\CollectionVisits;

use App\Enums\CollectionResult;
use App\Enums\CollectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CollectionVisits\IndexCollectionVisitRequest;
use App\Http\Requests\CollectionVisits\StoreCollectionVisitRequest;
use App\Models\Client;
use App\Models\CollectionVisit;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CollectionVisitController extends Controller
{
    public function index(IndexCollectionVisitRequest $request): View
    {
        $validated = $request->validated();

        $query = CollectionVisit::query()
            ->with(['client', 'loan', 'user'])
            ->when($validated['search'] ?? '', fn ($q) => $q->whereHas('client', fn ($c) => $c->search($validated['search'] ?? '')))
            ->when($validated['result'] ?? '', fn ($q) => $q->where('result', $validated['result']))
            ->when($validated['type'] ?? '', fn ($q) => $q->where('type', $validated['type']))
            ->when($validated['from'] ?? '', fn ($q) => $q->whereDate('visited_at', '>=', $validated['from']))
            ->when($validated['to'] ?? '', fn ($q) => $q->whereDate('visited_at', '<=', $validated['to']))
            ->latest('visited_at')
            ->latest('id');

        $visits = $query->paginate(15)->withQueryString();

        $clients = Client::query()->active()->orderBy('name')->limit(200)->get();
        $loans = Loan::query()->active()->with('client')->limit(200)->get();
        $installments = Installment::query()->payable()->with('loan.client')->limit(200)->get();
        $pendingPayments = Payment::query()->where('verification_status', 'pendiente')->with('client')->limit(50)->get();

        return view('collection-visits.index', [
            'visits' => $visits,
            'search' => $validated['search'] ?? '',
            'result' => $validated['result'] ?? '',
            'type' => $validated['type'] ?? '',
            'from' => $validated['from'] ?? '',
            'to' => $validated['to'] ?? '',
            'clients' => $clients,
            'loans' => $loans,
            'installments' => $installments,
            'pendingPayments' => $pendingPayments,
            'results' => CollectionResult::cases(),
            'types' => CollectionType::cases(),
            'pendingContact' => CollectionVisit::pendingContact()->count(),
            'paymentReported' => CollectionVisit::paymentReported()->count(),
        ]);
    }

    public function create(): View
    {
        $clients = Client::query()->active()->orderBy('name')->limit(500)->get();
        $loans = Loan::query()->active()->with('client')->limit(200)->get();
        $installments = Installment::query()->payable()->with('loan.client')->limit(200)->get();

        return view('collection-visits.create', [
            'clients' => $clients,
            'loans' => $loans,
            'installments' => $installments,
            'types' => CollectionType::cases(),
            'results' => CollectionResult::cases(),
        ]);
    }

    public function store(StoreCollectionVisitRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['managed_by'] = auth()->id();

        CollectionVisit::create($data);

        return redirect()
            ->route('collection-visits.index')
            ->with('collection_success', 'Collection visit registered successfully.');
    }
}
