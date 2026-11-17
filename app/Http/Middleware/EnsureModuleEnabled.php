<?php

namespace App\Http\Middleware;

use App\Enums\Module;
use App\Services\ModuleManager;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuerzo por ruta del estado de un módulo. La capa Gate ya bloquea toda
 * habilidad de un módulo apagado; este middleware cubre las rutas que no
 * heredan el módulo desde su ability (por ejemplo, las exportaciones, que
 * comparten la habilidad transversal "exports.run").
 *
 * Devuelve 404 para no revelar que el módulo existe. Se usa con el alias
 * "module", por ejemplo: ->middleware('module:loans').
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $manager = app(ModuleManager::class);
        $role = $request->user()?->role;

        foreach ($modules as $key) {
            $module = Module::tryFrom($key);

            if ($module === null) {
                throw new InvalidArgumentException("Módulo desconocido en la ruta: {$key}.");
            }

            if (! $manager->enabled($module)) {
                abort(404);
            }

            if ($role !== null && ! $manager->roleAllows($role, $module)) {
                abort(404);
            }
        }

        return $next($request);
    }
}
