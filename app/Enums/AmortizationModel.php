<?php

namespace App\Enums;

enum AmortizationModel: string
{
    case FixedInterest = 'fixed_interest';
    case BalanceInterest = 'balance_interest';
    case French = 'french';

    public function label(): string
    {
        return match ($this) {
            self::FixedInterest => 'Interés fijo sobre monto original',
            self::BalanceInterest => 'Interés sobre saldo',
            self::French => 'Cuota fija (francés)',
        };
    }

    /**
     * The models AmortizationService can actually calculate. Every screen
     * derives its options from here, so a model whose plan still throws an
     * InvalidArgumentException cannot be offered and chosen by mistake. Adding
     * a case to this list is what publishes its support.
     *
     * @return array<int, self>
     */
    public static function implemented(): array
    {
        return [
            self::FixedInterest,
            self::BalanceInterest,
            self::French,
        ];
    }
}
