@extends('layouts.app')

@php
    use App\Enums\LoanStatus;
    use App\Services\Money;
@endphp

@section('content')
    <x-header icon="file-invoice" title="Préstamos" text="Cartera completa, estado de cada plan y acceso al detalle.">
        <x-slot:actions>
            @can('exports.run')
                <a href="{{ route('export.loans', request()->only(['search', 'status'])) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-download me-1"></i> Exportar
                </a>
            @endcan

            @can('loans.manage')
                <a href="{{ route('loans.create') }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-plus me-1"></i> Nuevo préstamo
                </a>
            @endcan
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <x-card title="Buscar y filtrar" icon="filter" color="secondary">
                <form method="GET" action="{{ route('loans.index') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-lg-8">
                        <label for="search" class="form-label small text-muted">Buscar</label>
                        <input type="search" class="form-control" id="search" name="search" value="{{ $search }}" placeholder="Cliente, teléfono o documento">
                    </div>

                    <div class="col-6 col-lg-2">
                        <label for="status" class="form-label small text-muted">Estado</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all">Todos</option>
                            @foreach (LoanStatus::cases() as $case)
                                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ti ti-search me-1"></i> Filtrar
                        </button>

                        @if ($search !== '' || $status !== 'all')
                            <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </x-card>

            <x-card title="Listado de préstamos" subtitle="{{ $loans->total() }} resultado(s)." icon="file-invoice" color="primary">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Préstamo</th>
                                <th>Cliente</th>
                                <th class="text-end">Monto</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Saldo</th>
                                <th>Cuotas</th>
                                <th>Inicio</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($loans as $loan)
                                <tr>
                                    <td class="fw-semibold text-nowrap">#{{ $loan->id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <x-avatar :name="$loan->client?->name ?? 'Cliente'" size="sm" />
                                            <div>
                                                <a href="{{ $loan->client ? route('clients.show', $loan->client) : '#' }}" class="fw-semibold text-decoration-none text-reset">
                                                    {{ $loan->client?->name ?? 'Cliente sin asignar' }}
                                                </a>
                                                <div class="small text-muted">{{ $loan->client?->phone ?? 'Sin teléfono' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end text-nowrap">{{ Money::format((float) $loan->principal_amount) }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format((float) $loan->total_amount) }}</td>
                                    <td class="text-end text-nowrap fw-semibold">{{ Money::format((float) $loan->pending_balance) }}</td>
                                    <td class="text-nowrap">
                                        <span class="badge rounded-pill text-bg-light border text-muted">{{ $loan->paid_installments_count }} / {{ $loan->installments_count }}</span>
                                    </td>
                                    <td class="text-nowrap text-muted small">{{ $loan->start_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td><x-status-badge :status="$loan->status" /></td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            @can('loans.view')
                                                <a href="{{ route('loans.show', $loan) }}" class="btn btn-sm btn-outline-secondary" title="Ver préstamo">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                                <a href="{{ route('print.loans.plan', $loan) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Imprimir plan (PDF)">
                                                    <i class="ti ti-printer"></i>
                                                </a>
                                            @endcan

                                            @can('loans.manage')
                                                @if ($loan->status->isEditable())
                                                    <a href="{{ route('loans.edit', $loan) }}" class="btn btn-sm btn-outline-secondary" title="Editar">
                                                        <i class="ti ti-pencil"></i>
                                                    </a>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <x-empty-state icon="file-invoice" title="No hay préstamos para mostrar" text="Probá con otros filtros o registrá el primer préstamo." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($loans->hasPages())
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-3">
                        <span class="small text-muted">Mostrando {{ $loans->firstItem() }}–{{ $loans->lastItem() }} de {{ $loans->total() }}</span>
                        {{ $loans->links() }}
                    </div>
                @endif
            </x-card>

        </div>
    </div>
@endsection
