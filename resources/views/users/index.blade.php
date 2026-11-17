@extends('layouts.app')

@php
    use App\Enums\Role;

    $roleColors = [
        Role::Admin->value => 'danger',
        Role::Supervisor->value => 'info',
        Role::Cajero->value => 'success',
        Role::Cobrador->value => 'warning',
    ];
@endphp

@section('content')
    <x-header icon="id-badge" title="Usuarios" text="Cuentas del equipo, roles y estado de acceso al sistema.">
        <x-slot:actions>
            @can('users.manage')
                <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-user-plus me-1"></i> Nuevo usuario
                </a>
            @endcan
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <x-card
                title="Listado de usuarios"
                subtitle="{{ $users->total() }} cuenta(s) del equipo."
                icon="users"
                color="primary"
            >
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Alta</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($users as $user)
                                @php
                                    $isSelf = auth()->id() === $user->id;
                                @endphp

                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <x-avatar :name="$user->name" size="sm" />

                                            <div>
                                                <div class="fw-semibold">
                                                    {{ $user->name }}
                                                    @if ($user->isSuperAdmin())
                                                        <span class="badge text-bg-danger ms-1" title="Puede administrar los módulos del sistema">
                                                            <i class="ti ti-shield-check me-1"></i> Superusuario
                                                        </span>
                                                    @endif
                                                    @if ($isSelf)
                                                        <span class="badge text-bg-light border text-muted ms-1">Vos</span>
                                                    @endif
                                                </div>
                                                <div class="small text-muted">ID #{{ $user->id }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="small">
                                        <a href="mailto:{{ $user->email }}" class="text-decoration-none text-reset">
                                            <i class="ti ti-mail me-1 text-muted"></i>{{ $user->email }}
                                        </a>
                                    </td>

                                    <td>
                                        <x-status-badge
                                            :status="$user->role?->value ?? 'unknown'"
                                            :label="$user->role?->label() ?? 'Sin rol'"
                                            :color="$roleColors[$user->role?->value] ?? 'secondary'"
                                        />
                                    </td>

                                    <td>
                                        @if ($user->active)
                                            <span class="badge rounded-pill text-bg-success">Activo</span>
                                        @else
                                            <span class="badge rounded-pill text-bg-secondary">Inactivo</span>
                                        @endif
                                    </td>

                                    <td class="small text-muted text-nowrap">
                                        {{ $user->created_at?->format('d/m/Y') }}
                                    </td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a
                                                href="{{ route('users.edit', $user) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Editar"
                                            >
                                                <i class="ti ti-pencil"></i>
                                            </a>

                                            @unless ($isSelf)
                                                <form
                                                    method="POST"
                                                    action="{{ route('users.toggle-active', $user) }}"
                                                    data-confirm-delete
                                                    data-confirm-title="{{ $user->active ? 'Desactivar usuario' : 'Reactivar usuario' }}"
                                                    data-confirm-text="{{ $user->active
                                                        ? 'Se cerrarán las sesiones abiertas de '.$user->name.' y no podrá iniciar sesión hasta que lo reactives.'
                                                        : $user->name.' va a poder iniciar sesión nuevamente.' }}"
                                                    data-confirm-button="{{ $user->active ? 'Sí, desactivar' : 'Sí, reactivar' }}"
                                                    data-confirm-cancel="Cancelar"
                                                >
                                                    @csrf

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm {{ $user->active ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                                        title="{{ $user->active ? 'Desactivar' : 'Reactivar' }}"
                                                    >
                                                        <i class="ti ti-{{ $user->active ? 'person-dash' : 'person-check' }}"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <x-empty-state
                                            icon="id-badge"
                                            title="No hay usuarios para mostrar"
                                            text="Creá la primera cuenta del equipo para empezar a operar."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-3">
                        <span class="small text-muted">
                            Mostrando {{ $users->firstItem() }}–{{ $users->lastItem() }} de {{ $users->total() }}
                        </span>

                        {{ $users->links() }}
                    </div>
                @endif
            </x-card>

            <x-card title="Sobre las cuentas" icon="info-circle" color="info">
                <ul class="small text-muted mb-0 ps-3">
                    <li class="mb-2">
                        Las cuentas del <strong>portal de clientes</strong> se crean desde la ficha del cliente,
                        no desde este módulo.
                    </li>
                    <li class="mb-2">
                        Las cuentas nunca se eliminan: se <strong>desactivan</strong> para conservar la trazabilidad
                        de la bitácora.
                    </li>
                    <li>
                        El sistema siempre conserva al menos un <strong>administrador activo</strong>.
                    </li>
                </ul>
            </x-card>

        </div>
    </div>
@endsection
