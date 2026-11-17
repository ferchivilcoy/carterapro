<?php

namespace App\Http\Controllers;

use App\Enums\Module;
use App\Enums\Role;
use App\Http\Requests\Settings\UpdateModuleAccessRequest;
use App\Http\Requests\Settings\UpdateModulesRequest;
use App\Services\ModuleManager;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;
use RealRashid\SweetAlert\Facades\Alert;

class ModuleController extends Controller
{
    public function __construct(
        private readonly ModuleManager $modules,
    ) {}

    public function index(): View
    {
        return view('modules.index', [
            'modules' => collect(Module::cases())
                ->map(fn (Module $module): array => [
                    'key' => $module->value,
                    'label' => $module->label(),
                    'description' => $module->description(),
                    'core' => $module->isCore(),
                    'enabled' => $this->modules->enabled($module),
                    'dependencies' => $this->labels($module->dependsOn()),
                    'dependents' => $this->dependentsOf($module),
                ])
                ->values(),
            'roles' => collect(Role::restrictableCases())
                ->map(fn (Role $role): array => [
                    'key' => $role->value,
                    'label' => $role->label(),
                ])
                ->values(),
            'accessModules' => collect(Module::restrictableCases())
                ->map(fn (Module $module): array => [
                    'key' => $module->value,
                    'label' => $module->label(),
                    'description' => $module->description(),
                ])
                ->values(),
            'roleAccess' => $this->roleAccessMatrix(),
        ]);
    }

    public function update(UpdateModulesRequest $request): RedirectResponse
    {
        $states = collect($request->validated('modules'))
            ->map(fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN))
            ->all();

        try {
            $this->modules->update($states);
        } catch (DomainException $exception) {
            return back()
                ->withErrors(['modules' => $exception->getMessage()])
                ->withInput();
        }

        Alert::success('Módulos actualizados', 'Los cambios se aplicaron correctamente.');

        return redirect()->route('modules.index');
    }

    public function updateRoleAccess(UpdateModuleAccessRequest $request): RedirectResponse
    {
        $matrix = collect($request->validated('access'))
            ->map(fn (array $modules): array => collect($modules)
                ->map(fn (mixed $allowed): bool => filter_var($allowed, FILTER_VALIDATE_BOOLEAN))
                ->all())
            ->all();

        try {
            $this->modules->updateRoleAccess($matrix);
        } catch (DomainException|InvalidArgumentException $exception) {
            return back()
                ->withErrors(['access' => $exception->getMessage()])
                ->withInput();
        }

        Alert::success('Accesos actualizados', 'Los permisos por rol se aplicaron correctamente.');

        return redirect()->route('modules.index');
    }

    /**
     * Matriz role => module => permitido, con las denegaciones efectivas para
     * que la pantalla muestre el mismo resultado que aplica el Gate.
     *
     * @return array<string, array<string, bool>>
     */
    private function roleAccessMatrix(): array
    {
        $matrix = [];

        foreach (Role::restrictableCases() as $role) {
            foreach (Module::restrictableCases() as $module) {
                $matrix[$role->value][$module->value] = $this->modules->roleAllows($role, $module);
            }
        }

        return $matrix;
    }

    /**
     * @param  array<int, Module>  $modules
     * @return array<int, string>
     */
    private function labels(array $modules): array
    {
        return array_map(fn (Module $module): string => $module->label(), $modules);
    }

    /**
     * Módulos que dependen del indicado, para mostrarlos en la pantalla y
     * advertir qué desactivar junto con él.
     *
     * @return array<int, string>
     */
    private function dependentsOf(Module $module): array
    {
        return $this->labels(array_values(array_filter(
            Module::cases(),
            fn (Module $candidate): bool => in_array($module, $candidate->dependsOn(), true),
        )));
    }
}
