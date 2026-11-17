<?php

namespace App\Http\Requests\Payments;

use App\Enums\Ability;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::PaymentsManage->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'installment_id' => ['required', 'exists:installments,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', new Enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:500'],
            'comprobante' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
            'verification_status' => ['nullable', 'in:pendiente,verificado'],
        ];
    }

    public function messages(): array
    {
        return [
            'installment_id.required' => 'Debe indicar la cuota a pagar.',
            'installment_id.exists' => 'La cuota indicada no existe.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'payment_date.before_or_equal' => 'La fecha de pago no puede ser futura.',
        ];
    }
}
