<?php

namespace App\Enums;

enum RateType: string
{
    case Total = 'total';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Total => 'Total',
            self::Monthly => 'Mensual',
        };
    }
}
