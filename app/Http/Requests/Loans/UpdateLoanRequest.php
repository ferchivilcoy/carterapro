<?php

namespace App\Http\Requests\Loans;

use App\Enums\Ability;
use App\Enums\AmortizationModel;
use App\Enums\DisbursementMethod;
use App\Enums\PaymentFrequency;
use App\Enums\RateType;
use App\Models\Setting;
use App\Services\Money;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Edición de un préstamo existente.
 *
 * Si el préstamo ya tiene pagos registrados, las condiciones del plan quedan
 * prohibidas: solo se pueden tocar la entrega y las notas. Cuando todavía no
 * hay pagos, se aplican las mismas reglas del alta (sin client_id, porque el
 * cliente del préstamo no cambia nunca).
 */
class UpdateLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::LoansManage->value) ?? false;
    }

    /**
     * La entrega en efectivo no tiene cuenta de destino: se descarta cualquier
     * valor que llegue de un campo viejo del formulario.
     */
    protected function prepareForValidation(): void
    {
        if ($this->string('disbursement_method')->toString() === DisbursementMethod::Cash->value) {
            $this->merge(['disbursement_account' => null]);
        }
    }

    public function rules(): array
    {
        if ($this->hasPayments()) {
            return $this->deliveryRules() + [
                'principal_amount' => ['prohibited'],
                'interest_rate' => ['prohibited'],
                'rate_type' => ['prohibited'],
                'amortization_model' => ['prohibited'],
                'installments_count' => ['prohibited'],
                'frequency' => ['prohibited'],
                'start_date' => ['prohibited'],
            ];
        }

        return [
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
                Rule::in(array_column(AmortizationModel::implemented(), 'value')),
            ],
            'installments_count' => ['required', 'integer', 'min:1', 'max:120'],
            'frequency' => ['required', new Enum(PaymentFrequency::class)],
            'start_date' => ['required', 'date'],
        ] + $this->deliveryRules();
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
            'principal_amount.required' => 'El monto del préstamo es obligatorio.',
            'principal_amount.min' => 'El monto debe ser al menos '.Money::format($this->minAmount()).'.',
            'principal_amount.max' => 'El monto no puede superar '.Money::format($this->maxAmount()).'.',
            'installments_count.min' => 'Debe haber al menos una cuota.',
            'installments_count.max' => 'El máximo de cuotas es 120.',
            'amortization_model.in' => 'El modelo de amortización seleccionado no es válido.',
            'disbursement_method.required' => 'Debe indicar cómo se entrega el dinero al cliente.',
            'disbursement_method' => 'La forma de entrega seleccionada no es válida.',
            'disbursement_account.required_if' => 'Seleccioná la cuenta de destino del cliente.',
            'principal_amount.prohibited' => 'No se pueden cambiar las condiciones del plan de un préstamo con pagos registrados.',
            'interest_rate.prohibited' => 'No se pueden cambiar las condiciones del plan de un préstamo con pagos registrados.',
            'rate_type.prohibited' => 'No se pueden cambiar las condiciones del plan de un préstamo con pagos registrados.',
            'amortization_model.prohibited' => 'No se pueden cambiar las condiciones del plan de un préstamo con pagos registrados.',
            'installments_count.prohibited' => 'No se pueden cambiar las condiciones del plan de un préstamo con pagos registrados.',
            'frequency.prohibited' => 'No se pueden cambiar las condiciones del plan de un préstamo con pagos registrados.',
            'start_date.prohibited' => 'No se pueden cambiar las condiciones del plan de un préstamo con pagos registrados.',
        ];
    }

    /**
     * Entrega y notas se pueden editar siempre, con o sin pagos.
     *
     * @return array<string, mixed>
     */
    private function deliveryRules(): array
    {
        return [
            'disbursement_method' => ['required', new Enum(DisbursementMethod::class)],
            'disbursement_account' => ['nullable', 'string', 'max:255', 'required_if:disbursement_method,transfer'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function hasPayments(): bool
    {
        $loan = $this->route('loan');

        return $loan !== null && $loan->payments()->exists();
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
