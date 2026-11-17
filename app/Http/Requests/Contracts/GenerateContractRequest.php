<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;

class GenerateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('contracts.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'loan_id' => ['required', 'exists:loans,id'],
        ];
    }
}
