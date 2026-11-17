<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Active = 'active';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
    case Refinanced = 'refinanced';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Paid => 'Pagado',
            self::Overdue => 'Vencido',
            self::Cancelled => 'Cancelado',
            self::Refinanced => 'Refinanciado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'primary',
            self::Paid => 'success',
            self::Overdue => 'error',
            self::Cancelled => 'neutral',
            self::Refinanced => 'secondary',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled, self::Refinanced]);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Active, self::Overdue]);
    }
}
