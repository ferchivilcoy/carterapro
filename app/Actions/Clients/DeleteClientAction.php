<?php

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeleteClientAction
{
    public function execute(Client $client): void
    {
        if ($client->hasActiveLoans()) {
            throw new RuntimeException('No se puede eliminar un cliente que tiene préstamos activos.');
        }

        DB::transaction(function () use ($client): void {
            // loans.client_id and payments.client_id are declared as restrictOnDelete
            // to keep the financial history safe by default, so the settled history
            // has to be purged explicitly: payments first, then the loans (their
            // installments cascade). Events are nullOnDelete and need no handling.
            $client->payments()->delete();
            $client->loans()->delete();

            // users.client_id is nullOnDelete, so the portal account would
            // survive the file as an active login with nothing behind it:
            // it is deactivated together with the client.
            $client->users()->update(['active' => false]);

            $client->delete();
        });
    }
}
