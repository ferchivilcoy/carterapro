@extends('layouts.app')

@section('content')
    <x-header icon="user-plus" title="Nuevo usuario" text="Creá una cuenta del equipo y asignale un rol.">
        <x-slot:actions>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver al listado
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <form method="POST" action="{{ route('users.store') }}">
                @csrf

                <div class="row">
                    <div class="col-12 col-xl-8">
                        <x-card
                            title="Datos de la cuenta"
                            subtitle="Los campos marcados con * son obligatorios."
                            icon="id-badge"
                            color="primary"
                        >
                            @include('users.partials.form', ['user' => null, 'roles' => $roles])

                            <div class="d-flex justify-content-end flex-wrap gap-2 border-top pt-3 mt-4">
                                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancelar</a>

                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-check me-1"></i> Crear usuario
                                </button>
                            </div>
                        </x-card>
                    </div>

                    <div class="col-12 col-xl-4">
                        <x-card title="Roles del sistema" icon="shield-check" color="info">
                            <dl class="small mb-0">
                                <dt class="fw-semibold">Administrador General</dt>
                                <dd class="text-muted mb-2">Acceso completo a todos los módulos y a la configuración.</dd>

                                <dt class="fw-semibold">Supervisor</dt>
                                <dd class="text-muted mb-2">Gestión de clientes, préstamos, cobranzas y reportes.</dd>

                                <dt class="fw-semibold">Cajero</dt>
                                <dd class="text-muted mb-2">Cobros, caja, contratos y documentos.</dd>

                                <dt class="fw-semibold">Cobrador</dt>
                                <dd class="text-muted mb-0">Sólo los clientes y préstamos que tiene asignados.</dd>
                            </dl>
                        </x-card>

                        <x-card title="Seguridad" icon="lock" color="warning">
                            <ul class="small text-muted mb-0 ps-3">
                                <li class="mb-2">La contraseña debe tener al menos 12 caracteres.</li>
                                <li class="mb-2">Al cambiar el rol o la contraseña se cierran las sesiones abiertas.</li>
                                <li>Podés desactivar la cuenta más adelante sin perder su historial.</li>
                            </ul>
                        </x-card>
                    </div>
                </div>
            </form>

        </div>
    </div>
@endsection
