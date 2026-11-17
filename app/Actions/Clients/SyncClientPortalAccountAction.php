<?php

namespace App\Actions\Clients;

use App\Enums\EventType;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Event;
use App\Models\User;

/**
 * Creates or updates the portal account (Cliente role) linked to a client
 * file. Both the create and the edit flows delegate here so the credentials,
 * the client link and the audit trail stay in a single place.
 *
 * Empty strings are treated as "not provided", so a form that only fills
 * some of the fields never wipes or half-creates an account.
 */
class SyncClientPortalAccountAction
{
    public function execute(Client $client, array $data): ?User
    {
        $user = $client->users()->first();

        $email = $this->stringOrNull($data['portal_email'] ?? null);
        $password = $this->stringOrNull($data['portal_password'] ?? null);

        if ($user === null) {
            return $email === null ? null : $this->createAccount($client, $email, $password);
        }

        return $this->updateAccount($client, $user, $email, $password, $data);
    }

    private function createAccount(Client $client, string $email, ?string $password): User
    {
        $user = User::create([
            'name' => $client->name,
            'email' => $email,
            'password' => $password,
            'role' => Role::Cliente,
            'client_id' => $client->id,
            'active' => true,
        ]);

        $this->logEvent(EventType::UserCreated, $client->id, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->label(),
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateAccount(Client $client, User $user, ?string $email, ?string $password, array $data): User
    {
        $changes = [];

        if ($email !== null && $email !== $user->email) {
            $user->email = $email;
            $changes[] = 'email';
        }

        if ($password !== null) {
            $user->password = $password;
            $changes[] = 'password';
        }

        if (array_key_exists('portal_active', $data) && (bool) $data['portal_active'] !== $user->active) {
            $user->active = (bool) $data['portal_active'];
            $changes[] = 'active';
        }

        if ($changes === []) {
            return $user;
        }

        $user->save();

        $this->logEvent(EventType::UserUpdated, $client->id, ['changes' => $changes]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $extraData
     */
    private function logEvent(EventType $type, int $clientId, array $extraData): void
    {
        Event::create([
            'event_type' => $type,
            'client_id' => $clientId,
            'extra_data' => $extraData,
            'created_at' => now(),
            'created_by' => auth()->id(),
        ]);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
