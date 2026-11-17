<?php

namespace App\Http\Requests\Settings;

use App\Enums\Ability;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::SettingsManage->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:100'],
            // mimes reads the file contents, so the extension the sender used
            // is irrelevant; raster formats only, no active content.
            'company_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_initial_amount' => ['nullable', 'numeric', 'min:0'],
            'default_interest_rate' => ['nullable', 'numeric', 'min:0'],
            'default_rate_type' => ['nullable', 'in:total,monthly'],
            'default_amortization_model' => ['nullable', 'in:fixed_interest,balance_interest,french'],
            'default_installments_count' => ['nullable', 'integer', 'min:1', 'max:120'],
            'default_frequency' => ['nullable', 'in:weekly,biweekly,monthly'],
            'currency' => ['nullable', 'string', 'max:3'],
            'overdue_grace_days' => ['nullable', 'integer', 'min:0'],
            'late_fee_monthly_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:60'],
            'reminder_email_enabled' => ['nullable', 'in:0,1'],
            'receipt_prefix' => ['nullable', 'string', 'max:10'],
            'score_weight_paid' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'score_weight_overdue' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'score_weight_loans' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'score_weight_ontime' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $keys = array_filter(Setting::scoreWeightKeys(), fn ($key) => array_key_exists($key, $this->validated()));
            $total = 0;

            foreach ($keys as $key) {
                $total += (float) $this->validated($key);
            }

            if ($total > 0 && abs($total - 100) > 0.001) {
                $validator->errors()->add(
                    'score_weight_paid',
                    'Los pesos del scoring deben sumar 100 (suman '.$total.').'
                );
            }
        });
    }
}
