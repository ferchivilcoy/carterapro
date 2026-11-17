<?php

namespace App\Services;

use App\Enums\EventType;
use App\Models\Event;

/**
 * Bitácora de seguridad: login, logout y recuperación de contraseña. Usa la
 * misma tabla de eventos para que el administrador los vea junto al resto de
 * la operación, sin agregar una infraestructura nueva.
 */
class SecurityEventLogger
{
    /**
     * @param  array<string, mixed>  $extraData
     */
    public function log(EventType $type, array $extraData = [], ?int $createdBy = null): void
    {
        Event::create([
            'event_type' => $type,
            'extra_data' => $extraData,
            'created_at' => now(),
            'created_by' => $createdBy,
        ]);
    }
}
