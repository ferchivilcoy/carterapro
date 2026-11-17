<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revoca el acceso persistido de una cuenta: sesiones web y tokens Sanctum.
 * Se usa al desactivar, al cambiar la contraseña y al cambiar el rol.
 */
class UserAccessRevoker
{
    /**
     * Cierra todas las sesiones y tokens del usuario.
     */
    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();

        $this->revokeSessions($user);
    }

    /**
     * Cierra todos los tokens y todas las sesiones del usuario excepto la
     * sesión actual, para no expulsar a quien está haciendo el cambio.
     */
    public function revokeOthers(User $user, ?string $currentSessionId = null): void
    {
        $user->tokens()->delete();

        $this->revokeSessions($user, $currentSessionId);
    }

    private function revokeSessions(User $user, ?string $currentSessionId = null): void
    {
        $table = (string) config('session.table', 'sessions');

        // SESSION_DRIVER puede ser file o array, y en ese caso no hay tabla
        // que borrar: el middleware EnsureUserIsActive igual frena la sesión.
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::connection(config('session.connection'))
            ->table($table)
            ->where('user_id', $user->getKey())
            ->when(
                $currentSessionId !== null,
                fn ($query) => $query->where('id', '!=', $currentSessionId),
            )
            ->delete();
    }
}
