<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Cancelled => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Cancelled => 'neutral',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Cancelled;
    }
}
