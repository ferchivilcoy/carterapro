<?php

namespace App\Http\Controllers\Api;

use App\Actions\Clients\CreateClientAction;
use App\Actions\Clients\DeleteClientAction;
use App\Actions\Clients\UpdateClientAction;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::query()->with('users');

        if ($search = $request->string('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $clients = $query->latest()->paginate(15)->withQueryString();

        return $this->success($clients, 'Clientes listados');
    }

    public function show(Client $client)
    {
        $client->load('loans.installments', 'loans.payments', 'users');

        return $this->success($client, 'Cliente encontrado');
    }

    public function store(StoreClientRequest $request, CreateClientAction $create)
    {
        $client = $create->execute($request->validated());

        return $this->success($client, 'Cliente creado', 201);
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClientAction $update)
    {
        $client = $update->execute($client, $request->validated());

        return $this->success($client, 'Cliente actualizado');
    }

    public function destroy(Client $client, DeleteClientAction $delete)
    {
        $delete->execute($client);

        return $this->success(null, 'Cliente eliminado');
    }
}
