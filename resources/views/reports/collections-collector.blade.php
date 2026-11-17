@extends('layouts.app')

@php
    use App\Services\Money;

    $hasFilters = $from !== now()->startOfMonth()->toDateString() || $to !== now()->toDateString();
    $totalNet = $totalCollected + $totalLateFees;
@endphp

@section('content')
    <x-header icon="id-badge" title="Cobranzas por cobrador" text="Clientes asignados, cartera y cobros del período por responsable.">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Reportes
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="users" color="primary" description="Clientes asignados" value="{{ number_format($totalClients, 0, ',', '.') }}" hint="Entre todos los cobradores" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="file-invoice" color="info" description="Préstamos activos" value="{{ number_format($totalActiveLoans, 0, ',', '.') }}" hint="En cartera" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="wallet" color="warning" description="Saldo pendiente" value="{{ Money::format($totalPending) }}" hint="Capital + interés" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="cash-coin" color="success" description="Total cobrado" value="{{ Money::format($totalNet) }}" hint="Capital + mora" />
                </div>
            </div>

            <x-card title="Filtrar el período" icon="filter" color="secondary">
                <form method="GET" action="{{ route('reports.collections-collector') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label for="from" class="form-label small text-muted">Desde</label>
                        <input type="date" class="form-control" id="from" name="from" value="{{ $from }}">
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="to" class="form-label small text-muted">Hasta</label>
                        <input type="date" class="form-control" id="to" name="to" value="{{ $to }}">
                    </div>

                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ti ti-search me-1"></i> Filtrar
                        </button>

                        @if ($hasFilters)
                            <a href="{{ route('reports.collections-collector') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </x-card>

            <x-card title="Resultado por cobrador" subtitle="{{ count($rows) }} cobrador(es) activo(s)." icon="id-badge" color="primary">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cobrador</th>
                                <th class="text-end">Clientes</th>
                                <th class="text-end">Préstamos activos</th>
                                <th class="text-end">Saldo pendiente</th>
                                <th class="text-end">Capital cobrado</th>
                                <th class="text-end">Mora cobrada</th>
                                <th class="text-end">Total cobrado</th>
                                <th class="text-end">Vencidas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <x-avatar :name="$row['collector']->name" size="sm" />
                                            <div>
                                                <div class="fw-semibold">{{ $row['collector']->name }}</div>
                                                <div class="small text-muted">{{ $row['collector']->email }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="text-end">{{ number_format($row['clients'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($row['activeLoans'], 0, ',', '.') }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format($row['pendingBalance']) }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format($row['collected']) }}</td>
                                    <td class="text-end text-nowrap text-warning">{{ Money::format($row['collectedLateFees']) }}</td>
                                    <td class="text-end text-nowrap fw-semibold">{{ Money::format($row['collectedTotal']) }}</td>
                                    <td class="text-end">
                                        <span class="badge rounded-pill {{ $row['overdueCount'] > 0 ? 'text-bg-danger' : 'text-bg-light border text-muted' }}">
                                            {{ number_format($row['overdueCount'], 0, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <x-empty-state icon="id-badge" title="Sin cobradores" text="No hay usuarios con rol Cobrador activos." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        @if ($rows !== [])
                            <tfoot class="table-light">
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end">{{ number_format($totalClients, 0, ',', '.') }}</th>
                                    <th class="text-end">{{ number_format($totalActiveLoans, 0, ',', '.') }}</th>
                                    <th class="text-end">{{ Money::format($totalPending) }}</th>
                                    <th class="text-end">{{ Money::format($totalCollected) }}</th>
                                    <th class="text-end">{{ Money::format($totalLateFees) }}</th>
                                    <th class="text-end">{{ Money::format($totalNet) }}</th>
                                    <th class="text-end">{{ number_format($totalOverdue, 0, ',', '.') }}</th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </x-card>

        </div>
    </div>
@endsection
