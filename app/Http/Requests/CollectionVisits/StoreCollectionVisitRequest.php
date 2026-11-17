<?php

namespace App\Http\Requests\CollectionVisits;

use App\Enums\CollectionResult;
use App\Enums\CollectionType;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('collection-visits.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'loan_id' => ['nullable', 'exists:loans,id'],
            'installment_id' => ['nullable', 'exists:installments,id'],
            'payment_id' => ['nullable', 'exists:payments,id'],
            'type' => ['required', 'in:'.implode(',', array_map(fn ($c) => $c->value, CollectionType::cases()))],
            'result' => ['required', 'in:'.implode(',', array_map(fn ($c) => $c->value, CollectionResult::cases()))],
            'visited_at' => ['required', 'date'],
            'visited_at_time' => ['nullable', 'date_format:H:i'],
            'commitment_date' => ['nullable', 'date'],
            'commitment_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        if (! empty($data['visited_at'])) {
            $data['visited_at'] = Carbon::parse($data['visited_at'])->toDateString();
        }

        return $data;
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'Select a client.',
            'type.required' => 'Select the visit type.',
            'result.required' => 'Select the visit result.',
        ];
    }
}
