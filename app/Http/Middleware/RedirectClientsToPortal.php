<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the Cliente role out of the panel: any authenticated client that
 * lands on a guest-only screen (such as the login form) is sent to the
 * "Mi cuenta" portal instead of the dashboard, which would answer 403
 * because the role does not hold the dashboard.view ability.
 */
class RedirectClientsToPortal
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === Role::Cliente) {
            return redirect()->route('portal.index');
        }

        return $next($request);
    }
}
