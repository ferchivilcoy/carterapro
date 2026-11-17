<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * El flag "active" se evalúa en cada petición, no sólo al iniciar sesión: si
 * un administrador desactiva una cuenta, la sesión o el token que ya estaban
 * abiertos dejan de servir en el acto.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->active) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json([
                'message' => 'Tu cuenta está desactivada. Contactá a un administrador.',
            ], 401);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors(['email' => 'Tu cuenta está desactivada. Contactá a un administrador.']);
    }
}
