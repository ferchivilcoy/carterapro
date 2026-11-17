@extends('layouts.app')

@section('content')
    <x-header icon="user-cog" title="Editar usuario" text="{{ $user->name }} · {{ $user->role?->label() }}">
        <x-slot:actions>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver al listado
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <form method="POST" action="{{ route('users.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-12 col-xl-8">
                        <x-card
                            title="Datos de la cuenta"
                            subtitle="Dejá la contraseña en blanco para conservar la actual."
                            icon="id-badge"
                            color="primary"
                        >
                            @include('users.partials.form', ['user' => $user, 'roles' => $roles])

                            <div class="d-flex justify-content-end flex-wrap gap-2 border-top pt-3 mt-4">
                                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancelar</a>

                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-circle-check me-1"></i> Guardar cambios
                                </button>
                            </div>
                        </x-card>
                    </div>

                    <div class="col-12 col-xl-4">
                        <x-card title="Resumen de la cuenta" icon="clipboard-data" color="secondary">
                            <dl class="row small mb-0">
                                <dt class="col-5 text-muted fw-normal">Usuario</dt>
                                <dd class="col-7">#{{ $user->id }}</dd>

                                <dt class="col-5 text-muted fw-normal">Rol actual</dt>
                                <dd class="col-7">{{ $user->role?->label() ?? '—' }}</dd>

                                <dt class="col-5 text-muted fw-normal">Estado</dt>
                                <dd class="col-7">
                                    @if ($user->active)
                                        <span class="badge rounded-pill text-bg-success">Activo</span>
                                    @else
                                        <span class="badge rounded-pill text-bg-secondary">Inactivo</span>
                                    @endif
                                </dd>

                                <dt class="col-5 text-muted fw-normal">Alta</dt>
                                <dd class="col-7">{{ $user->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>

                                <dt class="col-5 text-muted fw-normal">Última edición</dt>
                                <dd class="col-7 mb-0">{{ $user->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                            </dl>
                        </x-card>

                        <x-card title="Sesiones" icon="shield-lock" color="warning">
                            <p class="small text-muted mb-0">
                                Si cambiás la contraseña o el rol, se cerrarán todas las sesiones de esta cuenta
                                excepto la tuya. Desactivarla además corta cualquier acceso abierto de inmediato.
                            </p>
                        </x-card>
                    </div>
                </div>
            </form>

        </div>
    </div>
@endsection
