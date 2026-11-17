<?php

namespace App\Http\Requests\Clients;

use App\Enums\Ability;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class StoreClientRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // El select "Sin asignar" viaja como cadena vacía: se normaliza a
        // null para que nunca se escriba un valor sucio en la columna FK.
        if ($this->input('assigned_collector_id') === '') {
            $this->merge(['assigned_collector_id' => null]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can(Ability::ClientsManage->value) ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:190'],
            'document' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9.\-_]+$/'],
            'cbu_cvu' => ['nullable', 'string', 'digits:22'],
            'bank_wallet' => ['nullable', 'string', 'max:100'],
            'account_holder' => ['nullable', 'string', 'max:150'],
            'is_own_account' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'portal_email' => [
                'nullable',
                'string',
                'email',
                'max:190',
                'unique:users,email',
                'required_with:portal_password',
            ],
            'portal_password' => [
                'nullable',
                'string',
                PasswordRule::defaults(),
                'confirmed',
                'required_with:portal_email',
            ],
        ];

        // Sólo el General Administrator gestiona la asignación a cobradores:
        // para el resto de los roles la clave ni siquiera entra a las reglas,
        // así que validated() no la incluye y la solicitud jamás la escribe.
        if ($this->user()?->isAdmin()) {
            $rules['assigned_collector_id'] = [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', Role::Cobrador->value),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'phone.required' => 'El teléfono es obligatorio.',
            'email.email' => 'El email de contacto no es válido.',
            'alias.regex' => 'El alias solo puede contener letras, números, puntos, guiones y guiones bajos.',
            'cbu_cvu.digits' => 'El CBU/CVU debe tener exactamente 22 dígitos.',
            'assigned_collector_id.exists' => 'El cobrador seleccionado no existe o no tiene ese rol.',
            'portal_email.email' => 'El email de acceso no es válido.',
            'portal_email.unique' => 'Ya existe un usuario con ese email.',
            'portal_email.required_with' => 'El email de acceso es obligatorio si cargás una contraseña.',
            'portal_password.min' => 'La contraseña de acceso debe tener al menos 12 caracteres.',
            'portal_password.confirmed' => 'Las contraseñas de acceso no coinciden.',
            'portal_password.required_with' => 'La contraseña de acceso es obligatoria si cargás el email.',
        ];
    }
}
