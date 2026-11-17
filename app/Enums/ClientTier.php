<?php

namespace App\Enums;

enum ClientTier: string
{
    case Initial = 'initial';
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';

    /**
     * Level that corresponds to a score on the 0-100 scale computed by
     * ClientScoreService, so the stored tier can never drift from the score.
     */
    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 75 => self::Gold,
            $score >= 50 => self::Silver,
            $score >= 25 => self::Bronze,
            default => self::Initial,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Inicial',
            self::Bronze => 'Bronce',
            self::Silver => 'Plata',
            self::Gold => 'Oro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Initial => 'secondary',
            self::Bronze => 'warning',
            self::Silver => 'info',
            self::Gold => 'success',
        };
    }
}
