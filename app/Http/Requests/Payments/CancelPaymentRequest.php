<?php

namespace App\Http\Requests\Payments;

use App\Enums\Ability;
use Illuminate\Foundation\Http\FormRequest;

class CancelPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::PaymentsManage->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Debe indicar el motivo de la anulación.',
            'cancellation_reason.min' => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
