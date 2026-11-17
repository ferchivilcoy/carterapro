<?php

namespace App\Http\Requests\ClientDocuments;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('client-documents.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'type' => ['required', 'in:'.implode(',', array_map(fn ($c) => $c->value, DocumentType::cases()))],
            'file' => ['required', 'file', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Select a file.',
            'file.max' => 'The file cannot exceed 10 MB.',
        ];
    }
}
