<?php

namespace App\Http\Middleware;

use App\Enums\Module;
use App\Services\ModuleManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El módulo `portal` se puede apagar desde Sistema → Módulos. En ese caso el
 * cliente no debe recibir un 403 seco: ve una pantalla que explica que el
 * portal está temporalmente desactivado y que sus datos siguen registrados.
 *
 * Se ejecuta antes del `can:clients.portal` para que el bloqueo por módulo
 * prima sobre la autorización por rol.
 */
class EnsurePortalAvailable
{
    public function __construct(
        private readonly ModuleManager $modules,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->modules->enabled(Module::Portal)) {
            return response()->view('portal.unavailable', [], 503);
        }

        return $next($request);
    }
}
