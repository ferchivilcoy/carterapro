<?php

namespace App\Http\Requests\Loans;

use App\Enums\Ability;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Anticipo a capital de un préstamo. El monto tiene que ser menor al saldo
 * pendiente: si cubre todo el saldo, corresponde registrar un pago normal.
 */
class PrepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::LoansManage->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', new Enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $loan = $this->route('loan');

                if ($loan === null) {
                    return;
                }

                $amount = (float) $this->string('amount')->toString();

                if ((float) $loan->pending_balance <= 0) {
                    $validator->errors()->add(
                        'amount',
                        'El préstamo no tiene saldo pendiente para anticipar.'
                    );

                    return;
                }

                if ($amount >= (float) $loan->pending_balance) {
                    $validator->errors()->add(
                        'amount',
                        'El anticipo debe ser menor al saldo pendiente. Usá el pago normal si cubre todo el saldo.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Indicá cuánto se anticipa a capital.',
            'amount.min' => 'El anticipo debe ser mayor a cero.',
            'payment_date.required' => 'La fecha del anticipo es obligatoria.',
        ];
    }
}
