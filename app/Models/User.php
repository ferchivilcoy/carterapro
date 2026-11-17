<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'client_id', 'active', 'is_super_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * The General Administrator role bypasses every ability through
     * Gate::before, so this flag is the single source of "access to
     * the whole system".
     */
    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * El superusuario es un Administrador General con un permiso adicional:
     * administrar los módulos del sistema. El rol sigue siendo la fuente del
     * resto de los accesos, así que ambos deben coincidir.
     */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && (bool) $this->is_super_admin;
    }

    /**
     * Collectors only see the clients assigned to them, their loans, their
     * installments and their payments (see VisibleToAssignedCollectorScope).
     */
    public function isCollector(): bool
    {
        return $this->role?->isCollector() ?? false;
    }

    /**
     * The Client record this account belongs to. Only set for accounts with
     * the Cliente role, which use it to scope the portal to their own data.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Landing page for the account. The Cliente role has no access to the
     * panel, so its home is the "Mi cuenta" portal instead of the dashboard;
     * every other role enters through the dashboard.
     */
    public function homeRoute(): string
    {
        return $this->role === Role::Cliente
            ? route('portal.index')
            : route('dashboard');
    }
}
