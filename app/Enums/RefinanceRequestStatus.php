<?php

namespace App\Enums;

enum RefinanceRequestStatus: string
{
    case Pending = 'pending';
    case Attended = 'attended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Attended => 'Atendida',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Attended => 'success',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isFinal(): bool
    {
        return $this === self::Attended;
    }
}
