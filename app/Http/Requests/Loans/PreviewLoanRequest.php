<?php

namespace App\Http\Requests\Loans;

use App\Enums\Ability;
use App\Enums\AmortizationModel;
use App\Enums\PaymentFrequency;
use App\Enums\RateType;
use App\Models\Setting;
use App\Services\Money;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Validación de la vista previa del plan: solo los campos que alimentan al
 * AmortizationService, sin cliente ni entrega, porque no se persiste nada.
 * Las reglas y los mensajes replican StoreLoanRequest para que el preview y
 * el alta definitiva no puedan aceptar datos distintos.
 */
class PreviewLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::LoansManage->value) ?? false;
    }

    public function rules(): array
    {
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
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
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
