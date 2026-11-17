<?php

namespace App\Actions\Clients;

trait NormalizesNullableStrings
{
    private array $nullableClientFields = [
        'email',
        'document',
        'address',
        'alias',
        'cbu_cvu',
        'bank_wallet',
        'account_holder',
        'notes',
    ];

    /**
     * Convierte a null los campos opcionales que llegan como cadena vacía,
     * evitando que guiones como el CHECK de cbu_cvu (MySQL) se violen.
     */
    private function normalizeNullableStrings(array $data): array
    {
        foreach ($this->nullableClientFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->nullableString($data[$field]);
            }
        }

        return $data;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' ? null : $value;
    }
}
