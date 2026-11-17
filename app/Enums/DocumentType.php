<?php

namespace App\Enums;

enum DocumentType: string
{
    case Dni = 'dni';
    case PayStub = 'pay_stub';
    case AddressProof = 'address_proof';
    case Contract = 'contract';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Dni => 'DNI',
            self::PayStub => 'Pay stub',
            self::AddressProof => 'Address proof',
            self::Contract => 'Loan contract',
            self::Other => 'Other',
        };
    }
}
