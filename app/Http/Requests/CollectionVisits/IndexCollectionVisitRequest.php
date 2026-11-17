<?php

namespace App\Http\Requests\CollectionVisits;

use Illuminate\Foundation\Http\FormRequest;

class IndexCollectionVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('collection-visits.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
