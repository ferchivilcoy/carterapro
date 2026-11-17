<?php

namespace App\Actions\Clients;

use App\Enums\ClientTier;
use App\Enums\EventType;
use App\Models\Client;
use App\Models\Event;

class CreateClientAction
{
    use NormalizesNullableStrings;

    public function __construct(private readonly SyncClientPortalAccountAction $portalAccount) {}

    public function execute(array $data): Client
    {
        $data = $this->normalizeNullableStrings($data);

        $client = Client::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'document' => $data['document'] ?? null,
            'address' => $data['address'] ?? null,
            'alias' => $data['alias'] ?? null,
            'cbu_cvu' => $data['cbu_cvu'] ?? null,
            'bank_wallet' => $data['bank_wallet'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
            'is_own_account' => isset($data['is_own_account']) ? (bool) $data['is_own_account'] : true,
            'notes' => $data['notes'] ?? null,
            'assigned_collector_id' => $data['assigned_collector_id'] ?? null,
            'tier' => ClientTier::Initial,
            'current_score' => 0,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        Event::create([
            'event_type' => EventType::ClientCreated,
            'client_id' => $client->id,
            'created_at' => now(),
            'created_by' => auth()->id(),
        ]);

        // Los campos portal_* no se filtran arriba (el array es explícito),
        // pero tampoco llegan a Client::create: se resuelven acá.
        $this->portalAccount->execute($client, $data);

        return $client;
    }
}
