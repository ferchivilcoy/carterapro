<?php

namespace App\Http\Requests\Settings;

use App\Enums\Ability;
use App\Enums\Module;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::ModulesManage->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'access' => ['required', 'array'],
            'access.*' => ['array'],
            'access.*.*' => ['required', 'boolean'],
        ];
    }

    /**
     * Las claves de rol y módulo no pasan por una regla estándar, así que se
     * comparan contra los catálogos restringibles para rechazar valores que no
     * correspondan a la matriz.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('access', []) as $roleKey => $modules) {
                $role = Role::tryFrom((string) $roleKey);

                if ($role === null || ! $role->isRestrictable()) {
                    $validator->errors()->add('access', "El rol «{$roleKey}» no admite restricciones.");

                    continue;
                }

                foreach (array_keys((array) $modules) as $moduleKey) {
                    $module = Module::tryFrom((string) $moduleKey);

                    if ($module === null || ! $module->isRestrictable()) {
                        $validator->errors()->add(
                            'access',
                            "El módulo «{$moduleKey}» no admite restricciones por rol.",
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'access.required' => 'No se recibió ningún acceso.',
            'access.array' => 'El formato de los accesos no es válido.',
            'access.*.array' => 'Los accesos de cada rol deben ser una lista de módulos.',
            'access.*.*.required' => 'Cada módulo debe indicar si está permitido.',
            'access.*.*.boolean' => 'El acceso de cada módulo debe ser permitido o denegado.',
        ];
    }
}
