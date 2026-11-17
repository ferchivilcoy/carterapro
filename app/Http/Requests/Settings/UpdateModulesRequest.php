<?php

namespace App\Http\Requests\Settings;

use App\Enums\Ability;
use App\Enums\Module;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::ModulesManage->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'modules' => ['required', 'array'],
            'modules.*' => ['required', 'boolean'],
        ];
    }

    /**
     * Las claves del arreglo no pasan por una regla de validación estándar, así
     * que se comparan contra el catálogo para rechazar módulos inexistentes.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach (array_keys((array) $this->input('modules', [])) as $key) {
                if (Module::tryFrom((string) $key) === null) {
                    $validator->errors()->add('modules', "El módulo «{$key}» no existe.");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'modules.required' => 'No se recibió ningún módulo.',
            'modules.array' => 'El formato de los módulos no es válido.',
            'modules.*.required' => 'Cada módulo debe indicar su estado.',
            'modules.*.boolean' => 'El estado de cada módulo debe ser activo o inactivo.',
        ];
    }
}
