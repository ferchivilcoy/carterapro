<?php

namespace Database\Seeders;

use App\Enums\ClientStatus;
use App\Enums\ClientTier;
use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One demo account per role so every permission profile can be exercised
 * without hand-creating users. Credentials come from config/admin.php.
 */
class DemoRoleUsersSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, email: string, role: Role}>
     */
    private const ACCOUNTS = [
        ['name' => 'Supervisor Demo', 'email' => 'supervisor@carterapro.test', 'role' => Role::Supervisor],
        ['name' => 'Cajero Demo', 'email' => 'cajero@carterapro.test', 'role' => Role::Cajero],
        ['name' => 'Cobrador Demo', 'email' => 'cobrador@carterapro.test', 'role' => Role::Cobrador],
    ];

    public function run(): void
    {
        // Doble red: aunque DatabaseSeeder ya lo condiciona, el seeder también
        // se niega a crear credenciales conocidas fuera de local/testing.
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoRoleUsersSeeder sólo se ejecuta en local/testing.');

            return;
        }

        $password = (string) config('admin.demo_password');

        foreach (self::ACCOUNTS as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $password,
                    'role' => $account['role']->value,
                    'active' => true,
                ]
            );
        }

        $this->seedDemoClientAccount($password);
    }

    /**
     * The Cliente demo needs a linked Client record, otherwise its portal
     * only shows the "cuenta sin vincular" state.
     */
    private function seedDemoClientAccount(string $password): void
    {
        $client = Client::query()->firstOrCreate(
            ['name' => 'Cliente Demo'],
            [
                'phone' => '0000000000',
                'status' => ClientStatus::Active,
                'tier' => ClientTier::Initial,
                'current_score' => 0,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'cliente@carterapro.test'],
            [
                'name' => 'Cliente Demo',
                'password' => $password,
                'role' => Role::Cliente->value,
                'client_id' => $client->id,
                'active' => true,
            ]
        );
    }
}
