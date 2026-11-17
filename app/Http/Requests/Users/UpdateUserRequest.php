<?php

namespace App\Http\Requests\Users;

use App\Enums\Ability;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::UsersManage->value) ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'string', 'email', 'max:190', 'unique:users,email,'.$userId],
            'password' => ['nullable', 'string', PasswordRule::defaults(), 'confirmed'],
            // El rol Cliente es de portal: se asigna automáticamente al crear
            // el cliente, nunca desde este módulo.
            'role' => ['sometimes', Rule::enum(Role::class)->except([Role::Cliente])],
            'active' => ['boolean'],
            // Ver StoreUserRequest: el flag sólo lo puede enviar un
            // superusuario.
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
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'role.required' => 'Debe indicar un rol para el usuario.',
            'role.enum' => 'El rol seleccionado no es válido.',
            'is_super_admin.prohibited' => 'Sólo un superusuario puede otorgar o quitar esa condición.',
        ];
    }
}
