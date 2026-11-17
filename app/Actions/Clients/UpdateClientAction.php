<?php

namespace App\Actions\Clients;

use App\Enums\EventType;
use App\Models\Client;
use App\Models\Event;
use Illuminate\Support\Arr;

class UpdateClientAction
{
    use NormalizesNullableStrings;

    private const PORTAL_FIELDS = [
        'portal_email',
        'portal_password',
        'portal_password_confirmation',
        'portal_active',
    ];

    public function __construct(private readonly SyncClientPortalAccountAction $portalAccount) {}

    public function execute(Client $client, array $data): Client
    {
        $data = $this->normalizeNullableStrings($data);

        // La cuenta de portal no pertenece a la ficha: se separa antes del
        // update para que no intente asignarle campos a Client ni aparezca
        // en la lista de cambios de la bitácora.
        $portalData = Arr::only($data, self::PORTAL_FIELDS);
        $data = Arr::except($data, self::PORTAL_FIELDS);

        $client->update(array_merge($data, [
            'updated_by' => auth()->id(),
        ]));

        $this->portalAccount->execute($client, $portalData);

        Event::create([
            'event_type' => EventType::ClientUpdated,
            'client_id' => $client->id,
            'extra_data' => ['changes' => array_keys($data)],
            'created_at' => now(),
            'created_by' => auth()->id(),
        ]);

        return $client->fresh();
    }
}
