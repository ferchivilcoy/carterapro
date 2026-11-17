<?php

namespace App\Http\Requests\Clients;

use App\Enums\Ability;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UpdateClientRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // El select "Sin asignar" viaja como cadena vacía: se normaliza a
        // null para que la asignación del administrador nunca escriba un
        // valor sucio en la columna FK.
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
        // La cuenta sólo se exige completa cuando el cliente todavía no tiene
        // ninguna y se está creando desde este formulario; si ya existe, la
        // contraseña queda opcional (en blanco = no cambiarla) y basta con el
        // email que se envía prefilled.
        $portalUser = $this->route('client')?->users()->first();
        $creatingAccount = $portalUser === null;

        $rules = [
            'name' => ['sometimes', 'string', 'max:150'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:190'],
            'document' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9.\-_]+$/'],
            'cbu_cvu' => ['nullable', 'string', 'digits:22'],
            'bank_wallet' => ['nullable', 'string', 'max:100'],
            'account_holder' => ['nullable', 'string', 'max:150'],
            'is_own_account' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'portal_email' => [
                $creatingAccount && $this->filled('portal_password') ? 'required' : 'nullable',
                'string',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($portalUser?->id),
            ],
            'portal_password' => [
                $creatingAccount && $this->filled('portal_email') ? 'required' : 'nullable',
                'string',
                PasswordRule::defaults(),
                'confirmed',
            ],
            'portal_active' => ['sometimes', 'boolean'],
        ];

        // Sólo el General Administrator gestiona la asignación a cobradores:
        // para el resto de los roles la clave ni siquiera entra a las reglas,
        // así que validated() no la incluye y la solicitud jamás la modifica.
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
            'email.email' => 'El email de contacto no es válido.',
            'portal_email.required' => 'El email de acceso es obligatorio si cargás una contraseña.',
            'portal_email.email' => 'El email de acceso no es válido.',
            'portal_email.unique' => 'Ya existe un usuario con ese email.',
            'portal_password.required' => 'La contraseña de acceso es obligatoria si cargás el email.',
            'portal_password.min' => 'La contraseña de acceso debe tener al menos 12 caracteres.',
            'portal_password.confirmed' => 'Las contraseñas de acceso no coinciden.',
        ];
    }
}
