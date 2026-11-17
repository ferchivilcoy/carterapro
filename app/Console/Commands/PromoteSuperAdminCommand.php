<?php

namespace App\Console\Commands;

use App\Enums\EventType;
use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Command;

class PromoteSuperAdminCommand extends Command
{
    protected $signature = 'users:promote-super-admin
        {email : Correo de una cuenta existente que pasará a ser superusuario}';

    protected $description = 'Otorga el permiso de superusuario (administrar módulos) a una cuenta existente';

    /**
     * Es el mecanismo de arranque: sin superusuarios nadie puede abrir la
     * pantalla de módulos, así que el primer flag se otorga desde la consola.
     * La cuenta debe existir; crear credenciales desde un comando sería un
     * riesgo innecesario.
     */
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No existe una cuenta con el correo {$email}.");
            $this->components->info('Creá la cuenta desde Usuarios y volvé a ejecutar el comando.');

            return self::FAILURE;
        }

        if ($user->isSuperAdmin()) {
            $this->components->info("{$user->name} ya es superusuario.");

            return self::SUCCESS;
        }

        if ($user->role !== Role::Admin) {
            $user->forceFill(['role' => Role::Admin])->save();

            $this->components->warn("Se cambió el rol de {$user->name} a {$user->role->label()}.");
        }

        $user->forceFill(['is_super_admin' => true])->save();

        Event::create([
            'event_type' => EventType::SuperAdminGranted,
            'extra_data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'source' => 'consola',
            ],
            'created_by' => null,
        ]);

        $this->components->info("{$user->name} ahora es superusuario.");

        if (! $user->active) {
            $this->components->warn('La cuenta está inactiva: activala desde Usuarios para que pueda iniciar sesión.');
        }

        return self::SUCCESS;
    }
}
