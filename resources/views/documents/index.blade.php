@extends('layouts.app')

@php
    $typeLabel = function ($type): string {
        $value = $type instanceof \BackedEnum ? $type->value : (string) $type;

        return match ($value) {
            'dni' => 'DNI',
            'pay_stub' => 'Recibo de sueldo',
            'address_proof' => 'Comprobante de domicilio',
            'contract' => 'Contrato de préstamo',
            'other' => 'Otro',
            default => '—',
        };
    };
@endphp

@section('content')
    <x-header icon="folder-open" title="Documentos" text="Documentación respaldatoria de los clientes, guardada en el disco privado.">
        <x-slot:actions>
            @can('clients.view')
                <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-users me-1"></i> Clientes
                </a>
            @endcan
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-md-4">
                    <x-stat
                        icon="folder-open"
                        color="primary"
                        description="Documentos"
                        value="{{ number_format($documents->total(), 0, ',', '.') }}"
                        hint="Archivos cargados"
                    />
                </div>

                <div class="col-12 col-md-4">
                    <x-stat
                        icon="users"
                        color="info"
                        description="Clientes"
                        value="{{ number_format($clients->count(), 0, ',', '.') }}"
                        hint="Disponibles para asociar"
                    />
                </div>

                <div class="col-12 col-md-4">
                    <x-stat
                        icon="tags"
                        color="secondary"
                        description="Tipos de documento"
                        value="{{ number_format(count($types), 0, ',', '.') }}"
                        hint="Categorías admitidas"
                    />
                </div>
            </div>

            @can('client-documents.manage')
                <x-card title="Subir documento" subtitle="El archivo se guarda en el disco privado y se sirve sólo por el sistema." icon="cloud-upload" color="success">
                    @if ($clients->isEmpty())
                        <x-empty-state
                            icon="user-plus"
                            title="Todavía no hay clientes"
                            text="Registrá un cliente antes de cargar documentación."
                        />

                        @can('clients.manage')
                            <div class="text-center">
                                <a href="{{ route('clients.create') }}" class="btn btn-outline-secondary">
                                    <i class="ti ti-user-plus me-1"></i> Nuevo cliente
                                </a>
                            </div>
                        @endcan
                    @else
                        <form method="POST" action="{{ route('client-documents.store') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                            @csrf

                            <div class="col-12 col-lg-3">
                                <label for="client_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                <select class="form-select @error('client_id') is-invalid @enderror" id="client_id" name="client_id" required>
                                    <option value="">Seleccioná un cliente</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>
                                            {{ $client->name }}{{ $client->document ? ' · DNI '.$client->document : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('client_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-lg-3">
                                <label for="type" class="form-label">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                                    <option value="">Seleccioná el tipo</option>
                                    @foreach ($types as $case)
                                        <option value="{{ $case->value }}" @selected(old('type') === $case->value)>{{ $typeLabel($case) }}</option>
                                    @endforeach
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-lg-4">
                                <label for="file" class="form-label">Archivo <span class="text-danger">*</span></label>
                                <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" required>
                                <div class="form-text">Hasta 10 MB por archivo.</div>
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-lg-2 d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="ti ti-cloud-upload me-1"></i> Subir
                                </button>
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notas</label>
                                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2" maxlength="1000" placeholder="Detalle opcional del documento">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </form>
                    @endif
                </x-card>
            @endcan

            <x-card title="Documentos cargados" subtitle="{{ $documents->total() }} registro(s)." icon="folder-open" color="primary">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Archivo</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Notas</th>
                                <th>Subido por</th>
                                <th>Fecha</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($documents as $document)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-danger-subtle text-danger-emphasis flex-shrink-0" style="width: 2rem; height: 2rem;">
                                                <i class="ti ti-file-invoice"></i>
                                            </span>

                                            <div class="overflow-hidden">
                                                <a href="{{ route('client-documents.download', $document) }}" class="fw-semibold text-decoration-none text-reset text-truncate d-block" style="max-width: 18rem;">
                                                    {{ $document->file_name }}
                                                </a>
                                                <div class="small text-muted text-truncate" style="max-width: 18rem;">{{ $document->path }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        @if ($document->client)
                                            <a href="{{ route('clients.show', $document->client) }}" class="text-decoration-none">{{ $document->client->name }}</a>
                                            <div class="small text-muted">{{ $document->client->phone }}</div>
                                        @else
                                            <span class="text-muted">Cliente sin asignar</span>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="badge rounded-pill text-bg-light border text-muted">{{ $typeLabel($document->type) }}</span>
                                    </td>

                                    <td class="small text-muted" style="max-width: 18rem;">
                                        {{ $document->notes ? \Illuminate\Support\Str::limit($document->notes, 90) : '—' }}
                                    </td>

                                    <td class="small text-muted text-nowrap">{{ $document->user?->name ?? 'Sistema' }}</td>
                                    <td class="small text-muted text-nowrap">{{ $document->created_at?->format('d/m/Y H:i') ?? '—' }}</td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="{{ route('client-documents.download', $document) }}" class="btn btn-sm btn-outline-secondary" title="Descargar documento">
                                                <i class="ti ti-download"></i>
                                            </a>

                                            @can('client-documents.manage')
                                                <form
                                                    method="POST"
                                                    action="{{ route('client-documents.destroy', $document) }}"
                                                    data-confirm-delete
                                                    data-confirm-title="Eliminar documento"
                                                    data-confirm-text="{{ 'Se eliminará el archivo '.$document->file_name.' de forma permanente.' }}"
                                                    data-confirm-button="Sí, eliminar"
                                                    data-confirm-cancel="Cancelar"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar documento">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <x-empty-state
                                            icon="folder-open"
                                            title="No hay documentos cargados"
                                            text="Subí el primer documento de un cliente para verlo acá."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($documents->hasPages())
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-3">
                        <span class="small text-muted">
                            Mostrando {{ $documents->firstItem() }}–{{ $documents->lastItem() }} de {{ $documents->total() }}
                        </span>

                        {{ $documents->links() }}
                    </div>
                @endif
            </x-card>

        </div>
    </div>
@endsection
