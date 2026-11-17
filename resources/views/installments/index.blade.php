@extends('layouts.app')

@php
    use App\Enums\InstallmentStatus;
    use App\Services\Money;

    $hasFilters = $search !== '' || $status !== 'all' || $period !== 'all';
@endphp

@section('content')
    <x-header icon="calendar-check" title="Cuotas" text="Plan de cuotas de toda la cartera, vencimientos y mora.">
        <x-slot:actions>
            @can('exports.run')
                <a href="{{ route('export.installments', request()->only(['search', 'status', 'period'])) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-download me-1"></i> Exportar
                </a>
            @endcan

            <a href="{{ route('print.installments', request()->only(['search', 'status', 'period'])) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-printer me-1"></i> Imprimir
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-md-4">
                    <x-stat
                        icon="list-numbers"
                        color="primary"
                        description="Resultados"
                        value="{{ number_format($installments->total(), 0, ',', '.') }}"
                        hint="Cuotas en el listado"
                    />
                </div>

                <div class="col-12 col-md-4">
                    <x-stat
                        icon="alert-triangle"
                        color="danger"
                        description="Vencidas"
                        value="{{ number_format(\App\Models\Installment::overdue()->count(), 0, ',', '.') }}"
                        hint="Cobranza prioritaria"
                        :href="route('installments.index', ['period' => 'overdue'])"
                    />
                </div>

                <div class="col-12 col-md-4">
                    <x-stat
                        icon="calendar-check"
                        color="success"
                        description="Vencen hoy"
                        value="{{ number_format(\App\Models\Installment::dueToday()->count(), 0, ',', '.') }}"
                        hint="Cobros del día"
                        :href="route('installments.index', ['period' => 'today'])"
                    />
                </div>
            </div>

            <x-card title="Buscar y filtrar" icon="filter" color="secondary">
                <form method="GET" action="{{ route('installments.index') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label for="search" class="form-label small text-muted">Buscar</label>
                        <input type="search" class="form-control" id="search" name="search" value="{{ $search }}" placeholder="Cliente, teléfono o documento">
                    </div>

                    <div class="col-6 col-lg-3">
                        <label for="status" class="form-label small text-muted">Estado</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all">Todos</option>
                            @foreach (InstallmentStatus::cases() as $case)
                                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-lg-3">
                        <label for="period" class="form-label small text-muted">Período</label>
                        <select class="form-select" id="period" name="period">
                            <option value="all" @selected($period === 'all')>Todos</option>
                            <option value="overdue" @selected($period === 'overdue')>Vencidas</option>
                            <option value="today" @selected($period === 'today')>Vencen hoy</option>
                            <option value="upcoming" @selected($period === 'upcoming')>Próximos 7 días</option>
                        </select>
                    </div>

                    <div class="col-12 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ti ti-search me-1"></i> Filtrar
                        </button>

                        @if ($hasFilters)
                            <a href="{{ route('installments.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </x-card>

            <x-card title="Listado de cuotas" subtitle="{{ $installments->total() }} resultado(s)." icon="calendar-check" color="primary">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cliente</th>
                                <th>Préstamo</th>
                                <th>Cuota</th>
                                <th>Vencimiento</th>
                                <th class="text-end">Esperado</th>
                                <th class="text-end">Pagado</th>
                                <th class="text-end">Pendiente</th>
                                <th class="text-end">Mora</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($installments as $installment)
                                @php
                                    $client = $installment->loan?->client;
                                @endphp

                                <tr class="{{ $installment->status === InstallmentStatus::Overdue ? 'table-danger' : '' }}">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <x-avatar :name="$client?->name ?? 'Cliente'" size="sm" />

                                            <div>
                                                <div class="fw-semibold">{{ $client?->name ?? 'Cliente sin asignar' }}</div>
                                                <div class="small text-muted">{{ $client?->phone ?? 'Sin teléfono' }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="text-nowrap">
                                        @if ($installment->loan)
                                            <a href="{{ route('loans.show', $installment->loan) }}" class="text-decoration-none">#{{ $installment->loan_id }}</a>
                                        @else
                                            #{{ $installment->loan_id }}
                                        @endif
                                    </td>

                                    <td class="text-nowrap fw-semibold">#{{ $installment->installment_number }}</td>

                                    <td class="text-nowrap">
                                        {{ $installment->due_date?->format('d/m/Y') }}
                                        @if ($installment->status === InstallmentStatus::Overdue)
                                            <span class="badge text-bg-danger ms-1">{{ $installment->daysOverdue() }} d</span>
                                        @endif
                                    </td>

                                    <td class="text-end text-nowrap">{{ Money::format((float) $installment->expected_amount) }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format((float) $installment->paid_amount) }}</td>
                                    <td class="text-end text-nowrap fw-semibold">{{ Money::format((float) $installment->pending_balance) }}</td>
                                    <td class="text-end text-nowrap {{ (float) $installment->late_fee > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                                        {{ Money::format((float) $installment->late_fee) }}
                                    </td>
                                    <td><x-status-badge :status="$installment->status" /></td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if ($installment->loan)
                                                <a href="{{ route('loans.show', $installment->loan) }}" class="btn btn-sm btn-outline-secondary" title="Ver préstamo">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                            @endif

                                            @can('payments.manage')
                                                @if ($installment->status->isPayable())
                                                    <a href="{{ route('payments.create', ['installment_id' => $installment->id]) }}" class="btn btn-sm btn-outline-success" title="Registrar pago">
                                                        <i class="ti ti-cash-coin"></i>
                                                    </a>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">
                                        <x-empty-state
                                            icon="calendar-check"
                                            title="No hay cuotas para mostrar"
                                            text="Probá con otros filtros o registrá un préstamo para generar su plan."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($installments->hasPages())
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-3">
                        <span class="small text-muted">
                            Mostrando {{ $installments->firstItem() }}–{{ $installments->lastItem() }} de {{ $installments->total() }}
                        </span>

                        {{ $installments->links() }}
                    </div>
                @endif
            </x-card>

        </div>
    </div>
@endsection
