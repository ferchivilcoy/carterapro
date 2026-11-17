<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityEventLogger;
use App\Services\UserAccessRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Recuperación de contraseña en dos pasos: pedir el enlace por correo y
 * elegir la contraseña nueva desde ese enlace. El token, su vencimiento y el
 * límite de reenvíos los maneja el broker "users" de config/auth.php.
 */
class PasswordResetController extends Controller
{
    private const GENERIC_STATUS = 'Si el correo existe, te enviamos un enlace para elegir una contraseña nueva.';

    public function __construct(
        private readonly SecurityEventLogger $security,
        private readonly UserAccessRevoker $accessRevoker,
    ) {}

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = (string) $request->string('email');
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            $user = User::query()->firstWhere('email', $email);

            if ($user !== null) {
                $this->security->log(
                    EventType::PasswordResetRequested,
                    $this->auditContext($request, $email),
                    $user->id,
                );
            }
        }

        // La misma respuesta para un correo existente y uno inexistente:
        // revelar la diferencia permitía enumerar cuentas registradas.
        return match ($status) {
            Password::RESET_THROTTLED => back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Ya pediste un enlace hace instantes. Esperá un minuto antes de reintentar.']),
            default => back()->with('status', self::GENERIC_STATUS),
        };
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->value(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $resetUser = null;

        $status = Password::reset($credentials, function (User $user, string $password) use (&$resetUser): void {
            $user->forceFill(['password' => Hash::make($password)])->save();

            $resetUser = $user;
        });

        if ($status === Password::PASSWORD_RESET && $resetUser !== null) {
            // Una contraseña nueva tiene que expulsar a cualquier sesión o
            // token que haya quedado abierto con la contraseña anterior.
            $this->accessRevoker->revokeAll($resetUser);

            $this->security->log(
                EventType::PasswordResetCompleted,
                $this->auditContext($request, $resetUser->email),
                $resetUser->id,
            );

            return redirect()
                ->route('login')
                ->with('status', 'Tu contraseña quedó actualizada. Ya podés iniciar sesión.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'El enlace no es válido o ya venció. Pedí uno nuevo.']);
    }

    /**
     * @return array{email: string, ip: ?string, user_agent: ?string}
     */
    private function auditContext(Request $request, string $email): array
    {
        return [
            'email' => $email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}
