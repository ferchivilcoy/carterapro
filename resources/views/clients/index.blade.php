@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
    <x-header icon="users" pretitle="Gestión" title="Clientes">
        <x-slot:actions>
            @can('exports.run')
                <a
                    href="{{ route('export.clients', ['search' => $search, 'status' => $status, 'tier' => $tier]) }}"
                    class="btn btn-outline-secondary">
                    <i class="ti ti-download me-1" ></i>
                    Exportar
                </a>
            @endcan

            @can('clients.manage')
                <a href="{{ route('clients.create') }}" class="btn btn-primary">
                    <i class="ti ti-plus me-1" ></i>
                    Nuevo cliente
                </a>
            @endcan
        </x-slot:actions>
    </x-header>

    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards mb-3">
                <div class="col-sm-6 col-md-3">
                    <x-stat color="primary" value="{{ number_format($stats['total'], 0, ',', '.') }}" description="Clientes">
                        <x-slot:icon>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-users"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 7a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" /><path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /><path d="M21 21v-2a4 4 0 0 0 -3 -3.85" /></svg>
                        </x-slot:icon>
                    </x-stat>
                </div>

                <div class="col-sm-6 col-md-3">
                    <x-stat color="success" value="{{ number_format($stats['active'], 0, ',', '.') }}" description="Clientes activos">
                        <x-slot:icon>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-users-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M3 21v-2a4 4 0 0 1 4 -4h4c.96 0 1.84 .338 2.53 .901" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /><path d="M16 19h6" /><path d="M19 16v6" /></svg>
                        </x-slot:icon>
                    </x-stat>
                </div>

                <div class="col-sm-6 col-md-3">
                    <x-stat color="danger" value="{{ number_format($stats['inactive'], 0, ',', '.') }}" description="Clientes inactivos">
                        <x-slot:icon>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-users-minus"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M3 21v-2a4 4 0 0 1 4 -4h4c.948 0 1.818 .33 2.504 .88" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /><path d="M16 19h6" /></svg>
                        </x-slot:icon>
                    </x-stat>
                </div>

                <div class="col-sm-6 col-md-3">
                    <x-stat color="warning" value="{{ number_format($stats['withActiveLoans'], 0, ',', '.') }}" description="Clientes con préstamos activos">
                        <x-slot:icon>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-wallet"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12" /><path d="M20 12v4h-4a2 2 0 0 1 0 -4h4" /></svg>
                        </x-slot:icon>
                    </x-stat>
                </div>
            </div>

            <x-card
                title="Listado de clientes"
                subtitle="Buscá por nombre, teléfono o documento y filtrá por estado o nivel."
                icon="users"
                color="primary"
                flush
            >
                <div class="card-body border-bottom">
                    <form method="GET" action="{{ route('clients.index') }}" class="row g-2 align-items-end">
                        <div class="col-12 col-lg-5">
                            <label for="search" class="form-label">Buscar</label>
                            <div class="input-icon">
                                <span class="input-icon-addon">
                                    <i class="ti ti-search" aria-hidden="true"></i>
                                </span>
                                <input
                                    type="search"
                                    id="search"
                                    name="search"
                                    class="form-control"
                                    value="{{ $search }}"
                                    placeholder="Nombre, teléfono o documento"
                                >
                            </div>
                        </div>

                        <div class="col-6 col-lg-3">
                            <label for="status" class="form-label">Estado</label>
                            <select id="status" name="status" class="form-select">
                                <option value="all" @selected($status === 'all')>Todos</option>
                                @foreach ($statuses as $statusCase)
                                    <option value="{{ $statusCase->value }}" @selected($status === $statusCase->value)>
                                        {{ $statusCase->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-lg-2">
                            <label for="tier" class="form-label">Nivel</label>
                            <select id="tier" name="tier" class="form-select">
                                <option value="all" @selected($tier === 'all')>Todos</option>
                                @foreach ($tiers as $tierCase)
                                    <option value="{{ $tierCase->value }}" @selected($tier === $tierCase->value)>
                                        {{ $tierCase->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-lg-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="ti ti-filter me-1" aria-hidden="true"></i>
                                Filtrar
                            </button>

                            @if ($search !== '' || $status !== 'all' || $tier !== 'all')
                                <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                    <i class="ti ti-x" aria-hidden="true"></i>
                                    <span class="visually-hidden">Limpiar filtros</span>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Contacto</th>
                                <th>Cuenta</th>
                                <th>Cobrador</th>
                                <th>Nivel</th>
                                <th>Estado</th>
                                <th class="text-center">Préstamos</th>
                                <th class="text-end">Saldo</th>
                                <th>Alta</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($clients as $client)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <x-avatar :name="$client->name" size="sm" />

                                            <div style="min-width: 0;">
                                                <a
                                                    href="{{ route('clients.show', $client) }}"
                                                    class="fw-semibold text-reset text-decoration-none"
                                                >{{ $client->name }}</a>

                                                <div class="small text-secondary">
                                                    {{ $client->document ? 'DNI '.$client->document : 'Sin documento' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="small">
                                            <a href="tel:{{ $client->phone }}" class="text-reset text-decoration-none">
                                                <i class="ti ti-phone me-1 text-secondary" aria-hidden="true"></i>
                                                {{ $client->phone }}
                                            </a>
                                        </div>

                                        @if ($client->email)
                                            <div class="small text-secondary text-truncate" style="max-width: 14rem;">
                                                <i class="ti ti-mail me-1" aria-hidden="true"></i>
                                                {{ $client->email }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="small text-secondary">
                                        <div class="text-truncate" style="max-width: 16rem;">
                                            {{ $client->accountSummary() ?? 'Sin datos' }}
                                        </div>
                                    </td>

                                    <td class="small">
                                        {{ $client->collector?->name ?? 'Sin asignar' }}
                                    </td>

                                    <td>
                                        <x-status-badge :status="$client->tier" />
                                        <div class="small text-secondary mt-1">Score {{ $client->current_score }}</div>
                                    </td>

                                    <td><x-status-badge :status="$client->status" /></td>

                                    <td class="text-center">
                                        <span class="badge {{ $client->active_loans_count > 0 ? 'bg-primary-lt text-primary' : 'bg-secondary-lt text-secondary' }}">
                                            {{ $client->active_loans_count }}
                                        </span>
                                    </td>

                                    <td class="text-end text-nowrap">
                                        {{ \App\Services\Money::format((float) ($client->active_pending_balance ?? 0)) }}
                                    </td>

                                    <td class="small text-secondary text-nowrap">
                                        {{ $client->created_at?->format('d/m/Y') }}
                                    </td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a
                                                href="{{ route('clients.show', $client) }}"
                                                class="btn btn-sm btn-icon btn-outline-secondary"
                                                title="Ver ficha"
                                                aria-label="Ver ficha de {{ $client->name }}"
                                            >
                                                <i class="ti ti-eye" aria-hidden="true"></i>
                                            </a>

                                            @can('clients.manage')
                                                <a
                                                    href="{{ route('clients.edit', $client) }}"
                                                    class="btn btn-sm btn-icon btn-outline-secondary"
                                                    title="Editar"
                                                    aria-label="Editar {{ $client->name }}"
                                                >
                                                    <i class="ti ti-pencil" aria-hidden="true"></i>
                                                </a>

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

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-icon btn-outline-danger"
                                                        title="Eliminar"
                                                        aria-label="Eliminar {{ $client->name }}"
                                                    >
                                                        <i class="ti ti-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">
                                        <x-empty-state
                                            icon="users"
                                            title="No hay clientes para mostrar"
                                            text="Probá con otros filtros o registrá el primer cliente."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-slot:footer>
                    <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">
                        <p class="m-0 small text-secondary">
                            @if ($clients->total() > 0)
                                Mostrando {{ $clients->firstItem() }}–{{ $clients->lastItem() }}
                                de {{ $clients->total() }} {{ $clients->total() === 1 ? 'cliente' : 'clientes' }}
                            @else
                                Sin resultados
                            @endif
                        </p>

                        {{ $clients->links() }}
                    </div>
                </x-slot:footer>
            </x-card>
        </div>
    </div>
@endsection
