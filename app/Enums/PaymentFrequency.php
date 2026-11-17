<?php

namespace App\Enums;

enum PaymentFrequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Semanal',
            self::Biweekly => 'Quincenal',
            self::Monthly => 'Mensual',
        };
    }

    /** Días entre cuotas para el cálculo de vencimientos */
    public function daysBetween(): int
    {
        return match ($this) {
            self::Weekly => 7,
            self::Biweekly => 15,
            self::Monthly => 30,
        };
    }
}
