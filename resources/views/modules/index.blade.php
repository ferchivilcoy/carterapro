@extends('layouts.app')

@section('content')
    <x-header
        icon="toggle-right"
        title="Módulos"
        text="Activá o desactivá funcionalidades del sistema. Los cambios se aplican de inmediato."
    >
        <x-slot:actions>
            @can('settings.manage')
                <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-settings me-1"></i> Configuración
                </a>
            @endcan
        </x-slot:actions>
    </x-header>

    <div class="page-body">
        <div class="container-xl">

            @if ($errors->any())
                <div class="alert alert-danger d-flex gap-2" role="alert">
                    <i class="ti ti-alert-triangle flex-shrink-0"></i>

                    <div>
                        <p class="fw-semibold mb-1">No se pudieron guardar los cambios:</p>
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="alert alert-info d-flex gap-2" role="alert">
                <i class="ti ti-info-circle flex-shrink-0"></i>

                <div>
                    Desactivar un módulo <strong>no borra sus datos</strong>: sólo oculta su menú y bloquea el acceso.
                    Si desactivás un módulo, desactivá también los módulos que dependen de él.
                </div>
            </div>

            <form method="POST" action="{{ route('modules.update') }}">
                @csrf
                @method('PUT')

                <x-card
                    title="Catálogo de módulos"
                    subtitle="Los módulos núcleo sostienen el acceso al sistema y no pueden apagarse."
                    icon="apps"
                    color="primary"
                    flush
                >
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Módulo</th>
                                    <th>Depende de</th>
                                    <th>Requerido por</th>
                                    <th class="text-end">Estado</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($modules as $module)
                                    @php
                                        $isChecked = (bool) old(
                                            "modules.{$module['key']}",
                                            $module['enabled'] ? '1' : '0',
                                        );
                                    @endphp

                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $module['label'] }}</div>
                                            <div class="small text-secondary">{{ $module['description'] }}</div>
                                        </td>

                                        <td class="small">
                                            @forelse ($module['dependencies'] as $dependency)
                                                <span class="badge text-bg-light border">{{ $dependency }}</span>
                                            @empty
                                                <span class="text-muted">—</span>
                                            @endforelse
                                        </td>

                                        <td class="small">
                                            @forelse ($module['dependents'] as $dependent)
                                                <span class="badge text-bg-light border">{{ $dependent }}</span>
                                            @empty
                                                <span class="text-muted">—</span>
                                            @endforelse
                                        </td>

                                        <td class="text-end">
                                            @if ($module['core'])
                                                <input
                                                    type="hidden"
                                                    name="modules[{{ $module['key'] }}]"
                                                    value="1"
                                                >
                                                <span class="badge rounded-pill text-bg-secondary">
                                                    <i class="ti ti-lock me-1"></i> Siempre activo
                                                </span>
                                            @else
                                                <input
                                                    type="hidden"
                                                    name="modules[{{ $module['key'] }}]"
                                                    value="0"
                                                >
                                                <div class="form-check form-switch d-inline-flex align-items-center gap-2 mb-0">
                                                    <input
                                                        class="form-check-input mt-0"
                                                        type="checkbox"
                                                        role="switch"
                                                        id="module-{{ $module['key'] }}"
                                                        name="modules[{{ $module['key'] }}]"
                                                        value="1"
                                                        @checked($isChecked)
                                                    >
                                                    <label class="form-check-label" for="module-{{ $module['key'] }}">
                                                        {{ $isChecked ? 'Activo' : 'Inactivo' }}
                                                    </label>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>

                <div class="tile d-flex justify-content-end flex-wrap gap-2">
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Cancelar</a>

                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i> Guardar cambios
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('modules.role-access.update') }}" class="mt-4">
                @csrf
                @method('PUT')

                <x-card
                    title="Accesos por rol"
                    subtitle="Quitá un módulo a un rol para que no pueda verlo ni entrar, aunque el módulo esté activo."
                    icon="shield-lock"
                    color="warning"
                    flush
                >
                    <div class="card-body pb-0">
                        <div class="alert alert-warning d-flex gap-2 mb-0" role="alert">
                            <i class="ti ti-shield-lock flex-shrink-0"></i>

                            <div>
                                Las restricciones <strong>sólo quitan permisos</strong>, nunca otorgan acceso que el rol no tenga.
                                El <strong>Administrador</strong> y el <strong>Cliente</strong> conservan su acceso completo.
                                Al quitar un módulo, también se quitan los módulos que dependen de él.
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Módulo</th>
                                    @foreach ($roles as $role)
                                        <th class="text-center">{{ $role['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($accessModules as $module)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $module['label'] }}</div>
                                            <div class="small text-secondary">{{ $module['description'] }}</div>
                                        </td>

                                        @foreach ($roles as $role)
                                            @php
                                                $field = "access.{$role['key']}.{$module['key']}";
                                                $isAllowed = (bool) old(
                                                    $field,
                                                    $roleAccess[$role['key']][$module['key']] ? '1' : '0',
                                                );
                                            @endphp

                                            <td class="text-center">
                                                <input type="hidden" name="{{ $field }}" value="0">

                                                <div class="form-check form-switch d-inline-flex mb-0">
                                                    <input
                                                        class="form-check-input mt-0"
                                                        type="checkbox"
                                                        role="switch"
                                                        id="access-{{ $role['key'] }}-{{ $module['key'] }}"
                                                        name="{{ $field }}"
                                                        value="1"
                                                        @checked($isAllowed)
                                                    >
                                                    <label class="visually-hidden" for="access-{{ $role['key'] }}-{{ $module['key'] }}">
                                                        {{ $module['label'] }} para {{ $role['label'] }}
                                                    </label>
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>

                <div class="tile d-flex justify-content-end flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i> Guardar accesos
                    </button>
                </div>
            </form>

        </div>
    </div>
@endsection
