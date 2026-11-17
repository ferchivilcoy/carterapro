<?php

namespace App\Services;

use App\Enums\EventType;
use App\Enums\Module;
use App\Enums\Role;
use App\Models\Event;
use App\Models\Setting;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Única fuente de verdad del estado activo/inactivo de los módulos.
 *
 * El estado se persiste en la tabla settings con una clave por módulo y se
 * cachea como un mapa completo, porque la capa de autorización consulta el
 * estado en cada @can del menú y en cada petición a la API. Cualquier cambio
 * invalida el cache para que el efecto sea inmediato.
 *
 * La instancia se registra como singleton: el mapa se resuelve una sola vez
 * por petición y se reutiliza en todas las comprobaciones.
 */
final class ModuleManager
{
    private const CACHE_KEY = 'modules.states';

    private const ROLE_CACHE_KEY = 'modules.role_access';

    /**
     * @var array<string, bool>|null
     */
    private ?array $states = null;

    /**
     * Denegaciones explícitas por rol, indexadas por Role::value y con los
     * Module::value quitados. Ausente significa permitido.
     *
     * @var array<string, array<int, string>>|null
     */
    private ?array $roleAccess = null;

    /**
     * Denegaciones efectivas por rol (incluye los dependientes de un módulo
     * denegado), memoizadas por petición.
     *
     * @var array<string, array<string, bool>>
     */
    private array $effectiveDenied = [];

    /**
     * Estado de todos los módulos, indexado por Module::value.
     *
     * @return array<string, bool>
     */
    public function states(): array
    {
        return $this->states ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => $this->loadStates(),
        );
    }

    public function enabled(Module $module): bool
    {
        return $this->states()[$module->value];
    }

    /**
     * Estado global combinado con las restricciones del rol: un módulo sólo
     * queda disponible si está activo para todos y el rol no lo tiene quitado.
     *
     * @return array<string, bool>
     */
    public function statesForRole(?Role $role): array
    {
        $states = $this->states();

        if ($role === null) {
            return $states;
        }

        foreach ($states as $key => $enabled) {
            if ($enabled && ! $this->roleAllows($role, Module::from($key))) {
                $states[$key] = false;
            }
        }

        return $states;
    }

    /**
     * Indica si el rol conserva el módulo. El Administrador y el Cliente están
     * exentos, igual que los módulos núcleo: las restricciones sólo se aplican
     * a roles internos sobre módulos restringibles.
     */
    public function roleAllows(Role $role, Module $module): bool
    {
        if (! $role->isRestrictable() || ! $module->isRestrictable()) {
            return true;
        }

        return ! isset($this->effectiveDeniedForRole($role)[$module->value]);
    }

    public function roleDenied(Role $role, Module $module): bool
    {
        return ! $this->roleAllows($role, $module);
    }

    /**
     * Módulos efectivamente denegados al rol, incluidos los que dependen de
     * uno denegado. Un rol sin préstamos tampoco puede entrar a cuotas,
     * cobros, contratos ni rentabilidad.
     *
     * @return array<int, string>
     */
    public function roleDeniedModules(Role $role): array
    {
        if (! $role->isRestrictable()) {
            return [];
        }

        return array_keys($this->effectiveDeniedForRole($role));
    }

    /**
     * Persiste la matriz de accesos por rol de forma atómica.
     *
     * La matriz llega como role => module => permitido. Sólo se guardan las
     * denegaciones (valor '0'); volver a permitir un módulo borra su fila, de
     * modo que una instalación sin datos de acceso se comporta como siempre.
     * Cada cambio real deja un evento de auditoría en la misma transacción.
     *
     * @param  array<string, array<string, bool>>  $matrix
     */
    public function updateRoleAccess(array $matrix): void
    {
        $current = $this->roleAccess();
        $changes = [];

        foreach ($matrix as $roleKey => $modules) {
            $role = Role::tryFrom((string) $roleKey);

            if ($role === null || ! $role->isRestrictable()) {
                throw new InvalidArgumentException("Rol no restringible: {$roleKey}.");
            }

            foreach ((array) $modules as $moduleKey => $allowed) {
                $module = Module::tryFrom((string) $moduleKey);

                if ($module === null || ! $module->isRestrictable()) {
                    throw new InvalidArgumentException("Módulo no restringible: {$moduleKey}.");
                }

                $wasAllowed = ! in_array($module->value, $current[$role->value] ?? [], true);
                $willAllow = (bool) $allowed;

                if ($wasAllowed !== $willAllow) {
                    $changes[] = [$role, $module, $wasAllowed, $willAllow];
                }
            }
        }

        if ($changes === []) {
            return;
        }

        DB::transaction(function () use ($changes): void {
            foreach ($changes as [$role, $module, $wasAllowed, $willAllow]) {
                $key = $this->roleSettingKey($role, $module);

                if ($willAllow) {
                    Setting::query()->where('key', $key)->delete();
                } else {
                    Setting::set($key, '0');
                }

                Event::create([
                    'event_type' => EventType::ModuleAccessChanged,
                    'extra_data' => [
                        'role' => $role->value,
                        'role_label' => $role->label(),
                        'module' => $module->value,
                        'module_label' => $module->label(),
                        'allowed_before' => $wasAllowed,
                        'allowed_after' => $willAllow,
                    ],
                    'created_at' => now(),
                    'created_by' => auth()->id(),
                ]);
            }
        });

        $this->forgetCache();
    }

    public function enable(Module $module): void
    {
        $this->setEnabled($module, true);
    }

    public function disable(Module $module): void
    {
        $this->setEnabled($module, false);
    }

    /**
     * Persiste el estado de un solo módulo. Es un atajo de update() para los
     * lugares que no necesitan aplicar varios cambios a la vez.
     */
    public function setEnabled(Module $module, bool $enabled): void
    {
        $this->update([$module->value => $enabled]);
    }

    /**
     * Aplica un conjunto de cambios de forma atómica. A diferencia de un
     * setEnabled() por módulo, valida la configuración final: permite apagar
     * un módulo y sus dependientes en la misma operación, sin exigir un orden
     * específico al usuario.
     *
     * @param  array<string, bool>  $states  Estado deseado indexado por Module::value.
     */
    public function update(array $states): void
    {
        $current = $this->states();
        $target = $current;
        $changes = [];

        foreach ($states as $key => $enabled) {
            $module = Module::tryFrom((string) $key);

            if ($module === null) {
                throw new InvalidArgumentException("Módulo desconocido: {$key}.");
            }

            $target[$module->value] = (bool) $enabled;

            if ($current[$module->value] !== (bool) $enabled) {
                $changes[$module->value] = (bool) $enabled;
            }
        }

        if ($changes === []) {
            return;
        }

        $this->assertValidConfiguration($target, $changes);

        DB::transaction(function () use ($changes, $current): void {
            foreach ($changes as $key => $enabled) {
                $module = Module::from($key);

                Setting::set($module->settingKey(), $enabled ? '1' : '0');

                // El evento va en la misma transacción que el estado: si el
                // guardado falla, la Bitácora no registra un cambio fantasma.
                Event::create([
                    'event_type' => EventType::ModuleToggled,
                    'extra_data' => [
                        'module' => $module->value,
                        'module_label' => $module->label(),
                        'enabled_before' => $current[$key],
                        'enabled_after' => $enabled,
                    ],
                    'created_at' => now(),
                    'created_by' => auth()->id(),
                ]);
            }
        });

        $this->forgetCache();
    }

    /**
     * Descarta el mapa memoizado y su copia en el cache compartido. Se expone
     * para que las pruebas y los comandos que escriban settings directamente
     * puedan forzar una relectura.
     */
    public function forgetCache(): void
    {
        $this->states = null;
        $this->roleAccess = null;
        $this->effectiveDenied = [];

        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::ROLE_CACHE_KEY);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function roleAccess(): array
    {
        return $this->roleAccess ??= Cache::rememberForever(
            self::ROLE_CACHE_KEY,
            fn (): array => $this->loadRoleAccess(),
        );
    }

    /**
     * Lee sólo las denegaciones explícitas. La propagación a dependientes se
     * calcula al consultar, para que reactivar un módulo base no obligue a
     * limpiar filas derivadas.
     *
     * @return array<string, array<int, string>>
     */
    private function loadRoleAccess(): array
    {
        $keys = [];

        foreach (Role::restrictableCases() as $role) {
            foreach (Module::restrictableCases() as $module) {
                $keys[] = $this->roleSettingKey($role, $module);
            }
        }

        $stored = Setting::query()
            ->whereIn('key', $keys)
            ->pluck('value', 'key');

        $denied = [];

        foreach (Role::restrictableCases() as $role) {
            foreach (Module::restrictableCases() as $module) {
                $value = $stored->get($this->roleSettingKey($role, $module));
                $parsed = $value === null
                    ? null
                    : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($parsed === false) {
                    $denied[$role->value][] = $module->value;
                }
            }
        }

        return $denied;
    }

    /**
     * Denegaciones efectivas del rol: las explícitas más todos los módulos que
     * dependen de uno denegado. Se propaga en bucle hasta estabilizarse.
     *
     * @return array<string, bool>
     */
    private function effectiveDeniedForRole(Role $role): array
    {
        if (isset($this->effectiveDenied[$role->value])) {
            return $this->effectiveDenied[$role->value];
        }

        $denied = array_fill_keys($this->roleAccess()[$role->value] ?? [], true);

        do {
            $changed = false;

            foreach (Module::restrictableCases() as $module) {
                if (isset($denied[$module->value])) {
                    continue;
                }

                foreach ($module->dependsOn() as $dependency) {
                    if (isset($denied[$dependency->value])) {
                        $denied[$module->value] = true;
                        $changed = true;

                        break;
                    }
                }
            }
        } while ($changed);

        return $this->effectiveDenied[$role->value] = $denied;
    }

    private function roleSettingKey(Role $role, Module $module): string
    {
        return 'module_access.'.$role->value.'.'.$module->value;
    }

    /**
     * @return array<string, bool>
     */
    private function loadStates(): array
    {
        $stored = Setting::query()
            ->whereIn('key', array_map(
                fn (Module $module): string => $module->settingKey(),
                Module::cases(),
            ))
            ->pluck('value', 'key');

        $states = [];

        foreach (Module::cases() as $module) {
            $value = $stored->get($module->settingKey());
            $parsed = $value === null
                ? null
                : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            $states[$module->value] = $parsed ?? $module->defaultEnabled();
        }

        return $states;
    }

    /**
     * Valida la configuración resultante para los módulos que cambian. Se
     * revisa el estado final (no el orden de los cambios), de modo que un
     * apagado en bloque de un módulo y sus dependientes sea válido.
     *
     * @param  array<string, bool>  $target
     * @param  array<string, bool>  $changes
     */
    private function assertValidConfiguration(array $target, array $changes): void
    {
        foreach ($changes as $key => $enabled) {
            $module = Module::from($key);

            if ($module->isCore() && ! $enabled) {
                throw new DomainException(
                    "El módulo {$module->label()} es núcleo del sistema y no puede desactivarse."
                );
            }

            if ($enabled) {
                $disabledDependencies = array_values(array_filter(
                    $module->dependsOn(),
                    fn (Module $dependency): bool => ! $target[$dependency->value],
                ));

                if ($disabledDependencies === []) {
                    continue;
                }

                $labels = implode(', ', array_map(
                    fn (Module $dependency): string => $dependency->label(),
                    $disabledDependencies,
                ));

                throw new DomainException(
                    "Activá primero estos módulos de los que depende {$module->label()}: {$labels}."
                );
            }

            $enabledDependents = array_values(array_filter(
                Module::cases(),
                fn (Module $candidate): bool => $target[$candidate->value]
                    && in_array($module, $candidate->dependsOn(), true),
            ));

            if ($enabledDependents === []) {
                continue;
            }

            $labels = implode(', ', array_map(
                fn (Module $dependent): string => $dependent->label(),
                $enabledDependents,
            ));

            throw new DomainException(
                "No se puede desactivar {$module->label()}: todavía dependen de él {$labels}."
            );
        }
    }
}
