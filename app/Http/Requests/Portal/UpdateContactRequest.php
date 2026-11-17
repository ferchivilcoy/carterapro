<?php

namespace App\Http\Requests\Portal;

use App\Enums\Ability;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Actualización de los datos de contacto de la ficha del cliente (teléfono,
 * email y domicilio) desde el portal. Sólo toca columnas de contacto: el
 * cliente no puede mover su nombre, documento ni estado desde acá.
 */
class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::ClientPortal->value) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'El teléfono es obligatorio.',
            'phone.max' => 'El teléfono no puede superar los 30 caracteres.',
            'email.email' => 'El email de contacto no tiene un formato válido.',
            'email.max' => 'El email de contacto no puede superar los 190 caracteres.',
            'address.max' => 'El domicilio no puede superar los 255 caracteres.',
        ];
    }
}
