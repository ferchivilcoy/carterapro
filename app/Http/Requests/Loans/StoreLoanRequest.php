<?php

namespace App\Http\Requests\Loans;

use App\Enums\Ability;
use App\Enums\AmortizationModel;
use App\Enums\DisbursementMethod;
use App\Enums\PaymentFrequency;
use App\Enums\RateType;
use App\Models\Client;
use App\Models\Setting;
use App\Services\Money;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::LoansManage->value) ?? false;
    }

    /**
     * La entrega en efectivo no tiene cuenta de destino: se descarta cualquier
     * valor que llegue de un campo viejo del formulario para que nunca se
     * persista una cuenta junto a un pago en efectivo.
     */
    protected function prepareForValidation(): void
    {
        if ($this->string('disbursement_method')->toString() === DisbursementMethod::Cash->value) {
            $this->merge(['disbursement_account' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            // The amount limits are read from the settings table, so changing
            // them on the settings screen really moves the allowed range.
            'principal_amount' => [
                'required',
                'numeric',
                'min:'.$this->minAmount(),
                'max:'.$this->maxAmount(),
            ],
            'interest_rate' => ['required', 'numeric', 'min:0'],
            'rate_type' => ['required', new Enum(RateType::class)],
            'amortization_model' => [
                'required',
                new Enum(AmortizationModel::class),
                // Rejects a model whose plan AmortizationService cannot build,
                // even if it reaches this point outside of the form.
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
                    $client = Client::find($this->integer('client_id'));

                    if ($client === null || $client->accountSummary() === null) {
                        $validator->errors()->add(
                            'disbursement_account',
                            'El cliente no tiene datos bancarios cargados. Cargá CBU/CVU o alias para otorgar el préstamo por transferencia.'
                        );
                    }
                }

                $model = AmortizationModel::tryFrom($this->string('amortization_model')->toString());
                $needsPeriodicRate = in_array($model, [AmortizationModel::BalanceInterest, AmortizationModel::French], true);

                if ($needsPeriodicRate && $this->string('rate_type')->toString() === RateType::Total->value) {
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
            'client_id.required' => 'Debe seleccionar un cliente.',
            'client_id.exists' => 'El cliente seleccionado no existe.',
            'principal_amount.required' => 'El monto del préstamo es obligatorio.',
            'principal_amount.min' => 'El monto debe ser al menos '.Money::format($this->minAmount()).'.',
            'principal_amount.max' => 'El monto no puede superar '.Money::format($this->maxAmount()).'.',
            'installments_count.min' => 'Debe haber al menos una cuota.',
            'installments_count.max' => 'El máximo de cuotas es 120.',
            'amortization_model.in' => 'El modelo de amortización seleccionado no es válido.',
            'disbursement_method.required' => 'Debe indicar cómo se entrega el dinero al cliente.',
            'disbursement_method' => 'La forma de entrega seleccionada no es válida.',
            'disbursement_account.required_if' => 'Seleccioná la cuenta de destino del cliente.',
        ];
    }

    private function minAmount(): float
    {
        return Setting::getDecimal('min_amount', Setting::DEFAULTS['min_amount']);
    }

    private function maxAmount(): float
    {
        return Setting::getDecimal('max_initial_amount', Setting::DEFAULTS['max_initial_amount']);
    }
}
