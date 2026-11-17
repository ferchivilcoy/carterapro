<?php

namespace App\Http\Requests\CashMovements;

use Illuminate\Foundation\Http\FormRequest;

class IndexCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('cash-movements.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'type' => ['nullable', 'in:income,expense'],
            'status' => ['nullable', 'in:open,closed'],
        ];
    }
}
