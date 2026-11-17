<?php

namespace App\Http\Requests\Loans;

use App\Enums\Ability;
use App\Enums\AmortizationModel;
use App\Enums\DisbursementMethod;
use App\Enums\PaymentFrequency;
use App\Enums\RateType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Refinanciación de un préstamo. El monto del nuevo préstamo no se envía: el
 * server lo toma del saldo pendiente del préstamo original.
 */
class RefinanceLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::LoansManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->string('disbursement_method')->toString() === DisbursementMethod::Cash->value) {
            $this->merge(['disbursement_account' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'interest_rate' => ['required', 'numeric', 'min:0'],
            'rate_type' => ['required', new Enum(RateType::class)],
            'amortization_model' => [
                'required',
                new Enum(AmortizationModel::class),
                Rule::in(array_column(AmortizationModel::implemented(), 'value')),
            ],
            'installments_count' => ['required', 'integer', 'min:1', 'max:120'],
            'frequency' => ['required', new Enum(PaymentFrequency::class)],
            'start_date' => ['required', 'date'],
            'disbursement_method' => ['required', new Enum(DisbursementMethod::class)],
            'disbursement_account' => ['nullable', 'string', 'max:255', 'required_if:disbursement_method,transfer'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->string('disbursement_method')->toString() === DisbursementMethod::Transfer->value) {
                    $client = $this->route('loan')?->client;

                    if ($client === null || $client->accountSummary() === null) {
                        $validator->errors()->add(
                            'disbursement_account',
                            'El cliente no tiene datos bancarios cargados. Cargá CBU/CVU o alias para otorgar el préstamo por transferencia.'
                        );
                    }
                }

                $model = AmortizationModel::tryFrom($this->string('amortization_model')->toString());

                if ($model !== null
                    && in_array($model, [AmortizationModel::BalanceInterest, AmortizationModel::French], true)
                    && $this->string('rate_type')->toString() === RateType::Total->value) {
                    $validator->errors()->add(
                        'rate_type',
                        'El modelo "'.$model->label().'" usa la tasa por período. Seleccioná Tipo de tasa Mensual.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'installments_count.min' => 'Debe haber al menos una cuota.',
            'installments_count.max' => 'El máximo de cuotas es 120.',
            'amortization_model.in' => 'El modelo de amortización seleccionado no es válido.',
            'disbursement_method.required' => 'Debe indicar cómo se entrega el dinero al cliente.',
            'disbursement_account.required_if' => 'Seleccioná la cuenta de destino del cliente.',
            'interest_rate.required' => 'La tasa de interés es obligatoria.',
        ];
    }
}
