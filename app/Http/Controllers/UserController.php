<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Enums\Role;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Event;
use App\Models\User;
use App\Services\UserAccessRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RealRashid\SweetAlert\Facades\Alert;

class UserController extends Controller
{
    public function __construct(
        private readonly UserAccessRevoker $accessRevoker,
    ) {}

    /**
     * Las cuentas de portal (rol Cliente) no forman parte de este módulo:
     * se crean y administran desde el módulo de Clientes, así que quedan
     * fuera del listado y detrás del mismo abort en cada acción.
     */
    public function index(): View
    {
        $users = User::query()
            ->where('role', '!=', Role::Cliente->value)
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => Role::staffCases(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->safe()->merge([
            'active' => (bool) $request->boolean('active', true),
        ])->all();

        // El flag de superusuario exige el rol Administrador General: si no,
        // User::isSuperAdmin() lo ignoraría y la pantalla mostraría un permiso
        // que en realidad no existe.
        if (($data['is_super_admin'] ?? false) && $data['role'] !== Role::Admin->value) {
            Alert::error('No se pudo crear el usuario', 'Un superusuario debe tener el rol Administrador General.');

            return back()->withInput();
        }

        $user = User::create($data);

        Event::create([
            'event_type' => EventType::UserCreated,
            'extra_data' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->label(),
                'is_super_admin' => $user->isSuperAdmin(),
            ],
            'created_by' => $request->user()?->id,
        ]);

        Alert::success('Usuario creado', "Se registró a {$user->name}.");

        return redirect()->route('users.index');
    }

    public function edit(User $user): View
    {
        abort_if($user->role->isPortalAccount(), 404);

        return view('users.edit', [
            'user' => $user,
            'roles' => Role::staffCases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_if($user->role->isPortalAccount(), 404);

        $data = $request->safe()->all();
        $roleBefore = $user->role?->value;
        $passwordChanged = filled($data['password'] ?? null);

        if (! $passwordChanged) {
            unset($data['password']);
        }

        // El sistema nunca puede quedarse sin administrador activo: además de
        // no poder desactivarlo, el último admin no puede perder el rol.
        $targetRole = $data['role'] ?? $user->role->value;
        $superAdminBefore = $user->isSuperAdmin();
        $superAdminAfter = array_key_exists('is_super_admin', $data)
            ? (bool) $data['is_super_admin']
            : $superAdminBefore;

        // El flag de superusuario exige el rol Administrador General.
        if ($superAdminAfter && $targetRole !== Role::Admin->value) {
            Alert::error('No se pudo completar la operación', 'Un superusuario debe conservar el rol Administrador General.');

            return back()->withInput();
        }

        // Un superusuario no puede quitarse a sí mismo la condición: evita que
        // un error de edición deje el sistema sin nadie que administre módulos.
        if ($superAdminBefore && ! $superAdminAfter && $request->user()?->id === $user->id) {
            Alert::error('No se pudo completar la operación', 'No podés quitarte a vos mismo la condición de superusuario.');

            return back()->withInput();
        }

        // Tampoco el último superusuario activo puede perder el flag ni el rol.
        if (
            $superAdminBefore
            && $user->active
            && ($targetRole !== Role::Admin->value || ! $superAdminAfter)
            && $this->activeSuperAdminCount() === 1
        ) {
            Alert::error('No se pudo completar la operación', 'No podés quitarle la condición al último superusuario activo.');

            return back()->withInput();
        }

        if ($user->isAdmin() && $targetRole !== Role::Admin->value && $this->activeAdminCount() === 1) {
            Alert::error('No se pudo completar la operación', 'No podés quitarle el rol al último administrador activo.');

            return back()->withInput();
        }

        $changes = array_keys($data);
        $user->update($data);
        $roleAfter = $user->role?->value;
        $superAdminAfterSaved = $user->isSuperAdmin();

        // El cambio de flag se audita como un evento propio, además del
        // UserUpdated que registra la edición general.
        if ($superAdminBefore !== $superAdminAfterSaved) {
            Event::create([
                'event_type' => $superAdminAfterSaved
                    ? EventType::SuperAdminGranted
                    : EventType::SuperAdminRevoked,
                'extra_data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'created_by' => $request->user()?->id,
            ]);
        }

        // Cambiar el rol o la contraseña tiene que cortar el acceso que ya
        // estaba abierto: se conserva únicamente la sesión de quien lo edita.
        if ($roleBefore !== $roleAfter || $passwordChanged) {
            $this->accessRevoker->revokeOthers($user, $request->session()->getId());
        }

        Event::create([
            'event_type' => EventType::UserUpdated,
            'extra_data' => [
                'changes' => $changes,
                'user_id' => $user->id,
                'role_before' => $roleBefore,
                'role_after' => $roleAfter,
                'password_changed' => $passwordChanged,
            ],
            'created_by' => $request->user()?->id,
        ]);

        Alert::success('Usuario actualizado', "Se guardaron los cambios de {$user->name}.");

        return redirect()->route('users.index');
    }

    /**
     * Deactivating is preferred over deleting so the audit trail (created_by
     * on clients, loans, payments and events) keeps pointing at a real user.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        abort_if($user->role->isPortalAccount(), 404);

        if ($user->id === $request->user()->id) {
            Alert::error('No se pudo completar la operación', 'No podés desactivar tu propia cuenta.');

            return back();
        }

        if ($user->active && $user->isSuperAdmin() && $this->activeSuperAdminCount() === 1) {
            Alert::error('No se pudo completar la operación', 'No podés desactivar al último superusuario activo.');

            return back();
        }

        if ($user->active && $user->isAdmin() && $this->activeAdminCount() === 1) {
            Alert::error('No se pudo completar la operación', 'No podés desactivar al último administrador activo.');

            return back();
        }

        $wasActive = $user->active;
        $user->update(['active' => ! $wasActive]);

        if (! $user->active) {
            // Desactivar no alcanza con el flag: las sesiones y tokens que ya
            // estaban emitidos tienen que dejar de funcionar.
            $this->accessRevoker->revokeAll($user);
        }

        Event::create([
            'event_type' => EventType::UserUpdated,
            'extra_data' => [
                'changes' => ['active'],
                'user_id' => $user->id,
                'active_before' => $wasActive,
                'active_after' => $user->active,
            ],
            'created_by' => $request->user()?->id,
        ]);

        // Después del update, $user->active refleja el estado nuevo: si quedó
        // activo fue una reactivación, si quedó inactivo una desactivación.
        if ($user->active) {
            Alert::success('Usuario reactivado', "{$user->name} ya puede iniciar sesión.");
        } else {
            Alert::success('Usuario desactivado', "{$user->name} ya no puede iniciar sesión.");
        }

        return redirect()->route('users.index');
    }

    private function activeAdminCount(): int
    {
        return User::query()
            ->where('role', Role::Admin->value)
            ->where('active', true)
            ->count();
    }

    /**
     * Un sistema sin superusuarios activos no puede administrar sus módulos,
     * así que la misma protección del "último admin" se aplica al flag.
     */
    private function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('role', Role::Admin->value)
            ->where('is_super_admin', true)
            ->where('active', true)
            ->count();
    }
}
