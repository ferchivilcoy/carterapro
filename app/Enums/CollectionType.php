<?php

namespace App\Enums;

enum CollectionType: string
{
    case Call = 'call';
    case WhatsApp = 'whatsapp';
    case Visit = 'visit';
    case Message = 'message';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::WhatsApp => 'WhatsApp',
            self::Visit => 'Visit',
            self::Message => 'Message',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WhatsApp => 'primary',
            self::Call => 'info',
            self::Visit => 'warning',
            self::Message => 'neutral',
        };
    }
}
