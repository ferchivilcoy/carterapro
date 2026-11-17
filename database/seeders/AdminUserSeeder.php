<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea (o actualiza) el Administrador General con las variables ADMIN_*
     * de config/admin.php. Nunca usa una contraseña fija: si no está definida
     * se genera una aleatoria y se imprime una sola vez.
     */
    public function run(): void
    {
        $email = config('admin.email');
        $name = (string) config('admin.name');
        $password = config('admin.password');

        if (blank($email)) {
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'Definí ADMIN_EMAIL (y ADMIN_PASSWORD) antes de sembrar el administrador en producción.'
                );
            }

            $this->command?->warn('ADMIN_EMAIL no está definido: no se creó el administrador.');

            return;
        }

        $user = User::query()->firstWhere('email', $email);

        if ($user === null) {
            $password = filled($password) ? $password : Str::password(20);

            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => Role::Admin->value,
                'active' => true,
            ]);

            if (blank(config('admin.password'))) {
                $this->command?->warn(
                    "ADMIN_PASSWORD no estaba definido. Contraseña generada para {$email}: {$password}"
                );
            }

            return;
        }

        $attributes = [
            'name' => $name,
            'role' => Role::Admin->value,
            'active' => true,
        ];

        // Un re-seed no debe pisotear la contraseña si no se configuró una.
        if (filled($password)) {
            $attributes['password'] = $password;
        }

        $user->update($attributes);
    }
}
