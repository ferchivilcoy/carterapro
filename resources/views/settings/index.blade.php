@extends('layouts.app')

@php
    use App\Models\Setting;

    $logoPath = Setting::get('company_logo');
@endphp

@section('content')
    <x-header icon="settings" title="Configuración" text="Parámetros generales del sistema, valores por defecto y copias de seguridad.">
        <x-slot:actions>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver al dashboard
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            @if ($errors->any())
                <div class="alert alert-danger d-flex gap-2" role="alert">
                    <i class="ti ti-alert-triangle flex-shrink-0"></i>

                    <div>
                        <p class="fw-semibold mb-1">Revisá los datos ingresados:</p>
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row">
                    @foreach ($groups as $group)
                        <div class="col-12 {{ $group['key'] === 'company' ? 'col-xl-6' : 'col-xl-6' }}">
                            <x-card
                                title="{{ $group['label'] }}"
                                icon="{{ $group['icon'] }}"
                                color="{{ $group['theme'] }}"
                            >
                                <div class="row g-3">
                                    @foreach ($group['fields'] as $field)
                                        @if ($field['type'] === 'file')
                                            <div class="col-12">
                                                <label for="{{ $field['key'] }}" class="form-label">{{ $field['label'] }}</label>

                                                @if ($logoPath)
                                                    <div class="d-flex align-items-center gap-3 mb-2">
                                                        <img
                                                            src="{{ route('settings.logo') }}?v={{ md5($logoPath) }}"
                                                            alt="Logo actual"
                                                            class="border rounded p-1 bg-white"
                                                            style="max-height: 64px; max-width: 180px; object-fit: contain;"
                                                        >
                                                        <span class="small text-muted">Logo actual</span>
                                                    </div>
                                                @endif

                                                <input
                                                    type="file"
                                                    class="form-control @error($field['key']) is-invalid @enderror"
                                                    id="{{ $field['key'] }}"
                                                    name="{{ $field['key'] }}"
                                                    accept="image/png,image/jpeg,image/webp"
                                                >
                                                <div class="form-text">Si subís un archivo nuevo, reemplaza al logo actual.</div>
                                                @error($field['key'])
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        @elseif ($field['type'] === 'select')
                                            <div class="col-12 col-md-6">
                                                <label for="{{ $field['key'] }}" class="form-label">{{ $field['label'] }}</label>
                                                <select
                                                    class="form-select @error($field['key']) is-invalid @enderror"
                                                    id="{{ $field['key'] }}"
                                                    name="{{ $field['key'] }}"
                                                >
                                                    @foreach ($field['options'] as $optionValue => $optionLabel)
                                                        <option
                                                            value="{{ $optionValue }}"
                                                            @selected((string) old($field['key'], $field['value']) === (string) $optionValue)
                                                        >{{ $optionLabel }}</option>
                                                    @endforeach
                                                </select>
                                                @error($field['key'])
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        @else
                                            <div class="col-12 col-md-6">
                                                <label for="{{ $field['key'] }}" class="form-label">{{ $field['label'] }}</label>
                                                <input
                                                    type="{{ $field['type'] === 'number' ? 'number' : 'text' }}"
                                                    class="form-control @error($field['key']) is-invalid @enderror"
                                                    id="{{ $field['key'] }}"
                                                    name="{{ $field['key'] }}"
                                                    value="{{ old($field['key'], $field['value']) }}"
                                                    @if ($field['step']) step="{{ $field['step'] }}" @endif
                                                    @if ($field['min'] !== null) min="{{ $field['min'] }}" @endif
                                                    @if ($field['max'] !== null) max="{{ $field['max'] }}" @endif
                                                >
                                                @error($field['key'])
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </x-card>
                        </div>
                    @endforeach
                </div>

                <div class="tile d-flex justify-content-end flex-wrap gap-2">
                    <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">Descartar cambios</a>

                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i> Guardar configuración
                    </button>
                </div>
            </form>

            <div class="row">
                <div class="col-12 col-xl-8">
                    <x-card
                        title="Copias de seguridad"
                        subtitle="Se conservan las {{ \App\Services\DatabaseBackupService::KEEP }} copias más recientes."
                        icon="database"
                        color="secondary"
                    >
                        @if ($backups === [])
                            <x-empty-state
                                icon="database"
                                title="Todavía no hay copias"
                                text="Generá la primera copia de seguridad de la base de datos."
                            />
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Archivo</th>
                                            <th>Fecha</th>
                                            <th class="text-end">Tamaño</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($backups as $backup)
                                            <tr>
                                                <td class="small text-break">{{ $backup['name'] }}</td>
                                                <td class="small text-nowrap">{{ $backup['created_at']?->format('d/m/Y H:i') }}</td>
                                                <td class="small text-end text-nowrap">
                                                    {{ number_format($backup['size'] / 1024, 1, ',', '.') }} KB
                                                </td>
                                                <td class="text-end">
                                                    <a
                                                        href="{{ route('backup.download', $backup['name']) }}"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        title="Descargar copia"
                                                    >
                                                        <i class="ti ti-download"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-card>
                </div>

                <div class="col-12 col-xl-4">
                    <x-card title="Generar copia ahora" icon="database-plus" color="success">
                        <p class="small text-muted">
                            Se genera una copia de la base de datos actual y se guarda junto a las anteriores.
                            La más antigua se descarta automáticamente al superar el límite.
                        </p>

                        <form method="POST" action="{{ route('backup.run') }}">
                            @csrf

                            <button type="submit" class="btn btn-success w-100">
                                <i class="ti ti-database-plus me-1"></i> Generar copia de seguridad
                            </button>
                        </form>
                    </x-card>
                </div>
            </div>

        </div>
    </div>
@endsection
