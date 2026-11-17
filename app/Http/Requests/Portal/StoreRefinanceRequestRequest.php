<?php

namespace App\Http\Requests\Portal;

use App\Enums\Ability;
use App\Enums\RefinanceRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de refinanciación que el cliente levanta contra su propio
 * préstamo. El préstamo viene de la ruta, así que se inyecta en la validación
 * para poder chequear duplicados sin que el cliente pueda mandar un id
 * ajeno: el filtro por client_id deja pasar los préstamos de terceros y el
 * controlador responde 404 con el mismo criterio del resto del portal.
 */
class StoreRefinanceRequestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['loan_id' => $this->route('loan')]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can(Ability::ClientPortal->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:500'],
            'loan_id' => [
                'required',
                'integer',
                Rule::unique('refinance_requests', 'loan_id')
                    ->where('status', RefinanceRequestStatus::Pending->value)
                    ->where('client_id', $this->user()->client?->id ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.max' => 'El motivo no puede superar los 500 caracteres.',
            'loan_id.unique' => 'Ya enviaste una solicitud de refinanciación para este préstamo y sigue pendiente.',
        ];
    }
}
