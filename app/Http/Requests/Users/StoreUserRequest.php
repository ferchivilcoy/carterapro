<?php

namespace App\Http\Requests\Users;

use App\Enums\Ability;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::UsersManage->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', PasswordRule::defaults(), 'confirmed'],
            // El rol Cliente es de portal: se asigna automáticamente al crear
            // el cliente, nunca desde este módulo.
            'role' => ['required', Rule::enum(Role::class)->except([Role::Cliente])],
            'active' => ['boolean'],
            // El flag sólo viaja en el formulario cuando lo edita un
            // superusuario; cualquier otro actor que lo envíe es rechazado.
            'is_super_admin' => [
                'sometimes',
                'boolean',
                Rule::prohibitedIf(fn (): bool => ! ($this->user()?->isSuperAdmin() ?? false)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El email es obligatorio.',
            'email.unique' => 'Ya existe un usuario con ese email.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'role.required' => 'Debe indicar un rol para el usuario.',
            'role.enum' => 'El rol seleccionado no es válido.',
            'is_super_admin.prohibited' => 'Sólo un superusuario puede otorgar o quitar esa condición.',
        ];
    }
}
