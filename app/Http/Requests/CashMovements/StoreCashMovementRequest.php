<?php

namespace App\Http\Requests\CashMovements;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('cash-movements.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'concept' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Select the movement type.',
            'amount.required' => 'Enter the amount.',
            'concept.required' => 'Enter the concept.',
            'date.required' => 'Enter the date.',
        ];
    }
}
