<?php

namespace App\Providers;

use App\Enums\Ability;
use App\Enums\Module;
use App\Models\User;
use App\Services\ModuleManager;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // La aplicación usa Bootstrap 5 (plantilla Vali), así que los enlaces
        // de paginación tienen que generarse con esas clases y no con Tailwind.
        Paginator::useBootstrapFive();

        $this->registerAbilities();
        $this->registerPasswordPolicy();
        $this->registerPasswordResetMail();
    }

    /**
     * Política única para todas las altas y cambios de contraseña, incluidas
     * las cuentas de portal creadas desde el módulo de Clientes.
     */
    private function registerPasswordPolicy(): void
    {
        Password::defaults(fn () => Password::min(12));
    }

    /**
     * The General Administrator bypasses every check, so any ability added to
     * the catalog later is granted to it without extra wiring. Every other
     * role is consulted through Ability::value => Role::grants(), leaving
     * non-admin roles denied by default on anything not in their allow-list.
     *
     * The module check runs first because a disabled module must also deny the
     * administrator: hiding the menu is cosmetic, this is the real barrier.
     */
    private function registerAbilities(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            $knownAbility = Ability::tryFrom($ability);
            $module = $knownAbility ? Module::forAbility($knownAbility) : null;

            if ($module !== null) {
                $modules = app(ModuleManager::class);

                // El estado global apaga el módulo para todos; la restricción
                // por rol lo quita sólo a los roles internos configurados.
                if (! $modules->enabled($module)) {
                    return false;
                }

                if ($user->role !== null && ! $modules->roleAllows($user->role, $module)) {
                    return false;
                }
            }

            // Las habilidades exclusivas del superusuario no pasan por el
            // bypass del Admin: las resuelve su Gate::define con el flag.
            if ($knownAbility?->isSuperAdminOnly()) {
                return null;
            }

            return $user->isAdmin() ? true : null;
        });

        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, function (User $user) use ($ability): Response {
                if ($ability->isSuperAdminOnly()) {
                    return $user->isSuperAdmin()
                        ? Response::allow()
                        : Response::deny('Sólo un superusuario puede administrar los módulos del sistema.');
                }

                return $user->role->grants($ability->value)
                    ? Response::allow()
                    : Response::deny('No tenés permiso para realizar esta acción.');
            });
        }
    }

    /**
     * The framework ships the reset mail copy in English; this product is
     * Spanish, so subject, greeting, body and button are replaced here rather
     * than subclassing the notification.
     */
    private function registerPasswordResetMail(): void
    {
        ResetPasswordNotification::toMailUsing(function (User $notifiable, string $token): MailMessage {
            $expiresIn = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            $url = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            return (new MailMessage)
                ->subject('Elegí tu contraseña nueva - Credisoft')
                ->greeting('Hola '.$notifiable->name)
                ->line('Recibimos un pedido para restablecer la contraseña de tu cuenta en Credisoft.')
                ->action('Elegir contraseña nueva', $url)
                ->line("El enlace vence en {$expiresIn} minutos.")
                ->line('Si no pediste este cambio, ignorá este correo: tu contraseña actual sigue funcionando.')
                ->salutation('Saludos, Credisoft');
        });
    }
}
