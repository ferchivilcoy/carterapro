<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('payments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'verification_status' => ['required', 'in:verificado,rechazado'],
            'verification_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'verification_status.required' => 'Seleccioná el estado de verificación.',
            'verification_status.in' => 'Estado inválido.',
        ];
    }
}
