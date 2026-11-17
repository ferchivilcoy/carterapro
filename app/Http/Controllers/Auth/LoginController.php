<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Services\SecurityEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Límite vertical (misma cuenta) y horizontal (misma IP probando muchas
     * cuentas). El segundo es el que frena el credential stuffing.
     */
    private const EMAIL_ATTEMPTS = 5;

    private const IP_ATTEMPTS = 20;

    public function __construct(
        private readonly SecurityEventLogger $security,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $this->ensureNotRateLimited($request);

        if (! Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            $credentials['remember'] ?? false
        )) {
            $this->hit($request);

            $this->security->log(
                EventType::LoginFailed,
                $this->auditContext($request, $credentials['email']),
            );

            throw ValidationException::withMessages([
                'email' => 'Estas credenciales no coinciden con nuestros registros.',
            ]);
        }

        $this->clear($request);

        $user = $request->user();

        if (! $user->active) {
            $this->security->log(
                EventType::LoginFailed,
                $this->auditContext($request, $user->email) + ['reason' => 'inactive'],
                $user->id,
            );

            Auth::logout();

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta está desactivada. Contactá a un administrador.',
            ]);
        }

        $request->session()->regenerate();

        $this->security->log(
            EventType::LoginSucceeded,
            $this->auditContext($request, $user->email),
            $user->id,
        );

        return redirect()->intended($user->homeRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $this->security->log(EventType::Logout, $this->auditContext($request, $user->email), $user->id);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function ensureNotRateLimited(Request $request): void
    {
        foreach ($this->limits($request) as [$key, $attempts]) {
            if (! RateLimiter::tooManyAttempts($key, $attempts)) {
                continue;
            }

            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Probá de nuevo en {$seconds} segundos.",
            ]);
        }
    }

    private function hit(Request $request): void
    {
        foreach ($this->limits($request) as [$key]) {
            RateLimiter::hit($key);
        }
    }

    private function clear(Request $request): void
    {
        foreach ($this->limits($request) as [$key]) {
            RateLimiter::clear($key);
        }
    }

    /**
     * @return array<int, array{0: string, 1: int}>
     */
    private function limits(Request $request): array
    {
        return [
            [$this->throttleKey($request), self::EMAIL_ATTEMPTS],
            [$this->ipThrottleKey($request), self::IP_ATTEMPTS],
        ];
    }

    private function throttleKey(Request $request): string
    {
        return 'login:'.mb_strtolower($request->string('email')).'|'.$request->ip();
    }

    private function ipThrottleKey(Request $request): string
    {
        return 'login-ip:'.$request->ip();
    }

    /**
     * @return array{email: string, ip: ?string, user_agent: ?string}
     */
    private function auditContext(Request $request, ?string $email = null): array
    {
        return [
            'email' => $email ?? (string) $request->string('email'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}
