<?php

namespace App\Http\Controllers;

use App\Actions\Clients\CreateClientAction;
use App\Actions\Clients\DeleteClientAction;
use App\Actions\Clients\UpdateClientAction;
use App\Enums\ClientStatus;
use App\Enums\ClientTier;
use App\Enums\LoanStatus;
use App\Enums\Role;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RealRashid\SweetAlert\Facades\Alert;
use RuntimeException;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        // The filters are resolved to enum cases before they reach the query,
        // so an unknown value falls back to the unfiltered listing instead of
        // matching nothing.
        $search = $request->string('search', '');
        $status = $request->string('status', 'all');
        $tier = $request->string('tier', 'all');

        $selectedStatus = ClientStatus::tryFrom($status->toString());
        $selectedTier = ClientTier::tryFrom($tier->toString());

        $clients = Client::query()
            ->with('collector')
            ->withCount([
                'loans as active_loans_count' => fn ($query) => $query->where('status', LoanStatus::Active),
            ])
            ->withSum([
                'loans as active_pending_balance' => fn ($query) => $query->where('status', LoanStatus::Active),
            ], 'pending_balance')
            ->when($search->isNotEmpty(), fn ($query) => $query->search($search->toString()))
            ->when($selectedStatus, fn ($query) => $query->where('status', $selectedStatus))
            ->when($selectedTier, fn ($query) => $query->where('tier', $selectedTier))
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Las tarjetas resumen respetan el scope global del cobrador: muestran
        // sólo los clientes que ese rol tiene asignados.
        $stats = [
            'total' => Client::query()->count(),
            'active' => Client::query()->where('status', ClientStatus::Active)->count(),
            'inactive' => Client::query()->where('status', ClientStatus::Inactive)->count(),
            'withActiveLoans' => Client::query()
                ->whereHas('loans', fn ($query) => $query->where('status', LoanStatus::Active))
                ->count(),
        ];

        return view('clients.index', [
            'clients' => $clients,
            'stats' => $stats,
            'statuses' => ClientStatus::cases(),
            'tiers' => ClientTier::cases(),
            'search' => $search->toString(),
            'status' => $status->toString(),
            'tier' => $tier->toString(),
        ]);
    }

    public function create(): View
    {
        return view('clients.create', ['collectors' => $this->availableCollectors()]);
    }

    public function show(Client $client): View
    {
        $client->load([
            'loans' => fn ($query) => $query->withCount([
                'installments',
                'installments as paid_installments_count' => fn ($q) => $q->where('status', 'paid'),
            ])->orderByDesc('created_at'),
        ]);

        $payments = $client->payments()
            ->active()
            ->with(['loan', 'installment'])
            ->latest('payment_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $activeLoans = $client->loans
            ->filter(fn ($loan) => $loan->status->isEditable());

        return view('clients.show', [
            'client' => $client,
            'payments' => $payments,
            'portalAccount' => $client->users()->first(),
            'activeLoansCount' => $activeLoans->count(),
            'pendingBalance' => (float) $activeLoans->sum('pending_balance'),
            'totalPaid' => (float) $client->payments()->active()->sum('amount'),
        ]);
    }

    public function store(StoreClientRequest $request, CreateClientAction $create): RedirectResponse
    {
        $client = $create->execute($request->validated());

        Alert::success('Cliente registrado', "Se creó la ficha de {$client->name}.");

        return redirect()->route('clients.index');
    }

    public function edit(Client $client): View
    {
        return view('clients.edit', [
            'client' => $client,
            'collectors' => $this->availableCollectors(),
            'portalAccount' => $client->users()->first(),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClientAction $update): RedirectResponse
    {
        $client = $update->execute($client, $request->validated());

        Alert::success('Cliente actualizado', "Se guardaron los cambios de {$client->name}.");

        return redirect()->route('clients.index');
    }

    public function destroy(Client $client, DeleteClientAction $delete): RedirectResponse
    {
        try {
            $delete->execute($client);
        } catch (RuntimeException $e) {
            Alert::error('No se pudo eliminar', $e->getMessage());

            return back();
        }

        Alert::success('Cliente eliminado', "Se eliminó la ficha de {$client->name}.");

        return redirect()->route('clients.index');
    }

    /**
     * @return Collection<int, User>
     */
    private function availableCollectors(): Collection
    {
        return User::query()
            ->where('role', Role::Cobrador->value)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
