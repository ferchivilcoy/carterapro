<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * La cuenta de administrador sale de ADMIN_* y se puede sembrar en
     * cualquier entorno. Las cuentas demo y los datos de ejemplo son sólo
     * para local/testing: nunca deben existir en producción.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call(DemoRoleUsersSeeder::class);
        $this->call(CollectionVisitSeeder::class);
    }
}
