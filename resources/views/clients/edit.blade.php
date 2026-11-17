@extends('layouts.app')

@section('title', 'Editar cliente')

@section('content')
    <x-header icon="edit" title="Editar cliente" text="{{ $client->name }}">
        <x-slot:actions>
            <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver
            </a>

            <a href="{{ route('clients.show', $client) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-eye me-1"></i> Ver ficha
            </a>
        </x-slot:actions>
    </x-header>

    <div class="row">
        <div class="col-12 col-xl-8">
            {{-- El formulario de edición NO debe contener el formulario de borrado:
                 el navegador ignora los <form> anidados y el _method=DELETE termina
                 enviándose al guardar los cambios. --}}
            <form method="POST" action="{{ route('clients.update', $client) }}">
                @csrf
                @method('PUT')

                <x-card
                    title="Ficha del cliente"
                    subtitle="Los cambios quedan registrados en la bitácora."
                    icon="id-badge-2"
                    color="primary"
                >
                    @include('clients.partials.form', [
                        'client' => $client,
                        'collectors' => $collectors,
                        'portalAccount' => $portalAccount,
                    ])

                    <x-slot:footer>
                        <div class="d-flex justify-content-end flex-wrap gap-2">
                            <a href="{{ route('clients.show', $client) }}" class="btn btn-outline-secondary">
                                Cancelar
                            </a>

                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> Guardar cambios
                            </button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>

        <div class="col-12 col-xl-4">
            <x-card title="Resumen de la ficha" icon="clipboard-data" color="info">
                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary fw-normal">Estado</dt>
                    <dd class="col-7"><x-status-badge :status="$client->status" /></dd>

                    <dt class="col-5 text-secondary fw-normal">Nivel</dt>
                    <dd class="col-7"><x-status-badge :status="$client->tier" /></dd>

                    <dt class="col-5 text-secondary fw-normal">Score</dt>
                    <dd class="col-7 fw-semibold">{{ $client->current_score }} / 100</dd>

                    <dt class="col-5 text-secondary fw-normal">Cobrador</dt>
                    <dd class="col-7">{{ $client->collector?->name ?? 'Sin asignar' }}</dd>

                    <dt class="col-5 text-secondary fw-normal">Portal</dt>
                    <dd class="col-7">
                        @if ($portalAccount)
                            <span class="badge rounded-pill {{ $portalAccount->active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $portalAccount->active ? 'Habilitado' : 'Deshabilitado' }}
                            </span>
                            <div class="text-secondary mt-1 text-break">{{ $portalAccount->email }}</div>
                        @else
                            <span class="text-secondary">Sin cuenta</span>
                        @endif
                    </dd>

                    <dt class="col-5 text-secondary fw-normal">Alta</dt>
                    <dd class="col-7">{{ $client->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>

                    <dt class="col-5 text-secondary fw-normal">Última edición</dt>
                    <dd class="col-7">{{ $client->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </dl>

                @can('clients.view')
                    <div class="border-top pt-3 mt-3">
                        <a href="{{ route('print.clients.statement', $client) }}" class="btn btn-sm btn-outline-secondary w-100">
                            <i class="ti ti-printer me-1"></i> Imprimir estado de cuenta
                        </a>
                    </div>
                @endcan
            </x-card>

            @can('clients.manage')
                <x-card title="Zona de riesgo" icon="alert-triangle" color="danger">
                    <p class="small text-secondary">
                        Eliminar la ficha borra también el historial de préstamos y pagos asociado.
                        No se puede deshacer.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('clients.destroy', $client) }}"
                        data-confirm-delete
                        data-confirm-title="Eliminar cliente"
                        data-confirm-text="{{ 'Se eliminará la ficha de '.$client->name.' y todo su historial asociado. Esta acción no se puede deshacer.' }}"
                        data-confirm-button="Sí, eliminar"
                        data-confirm-cancel="Cancelar"
                    >
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="ti ti-trash me-1"></i> Eliminar cliente
                        </button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
@endsection
