@extends('layouts.app')

@php
    use App\Enums\LoanStatus;
    use App\Services\Money;

    $hasFilters = $search !== '' || $status !== 'all';
@endphp

@section('content')
    <x-header icon="folder-open" title="Estado de cartera" text="Composición de los préstamos, saldo pendiente y clientes con mayor deuda.">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Reportes
            </a>

            @can('exports.run')
                <a href="{{ route('export.loans', request()->only(['search', 'status'])) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-download me-1"></i> Exportar
                </a>
            @endcan
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="file-invoice" color="primary" description="Préstamos" value="{{ number_format($totalLoans, 0, ',', '.') }}" hint="Según los filtros" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="cash-banknote" color="info" description="Capital colocado" value="{{ Money::format($totalPrincipal) }}" hint="Monto original" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="wallet" color="warning" description="Saldo pendiente" value="{{ Money::format($pendingBalance) }}" hint="Capital + interés" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="alert-triangle" color="danger" description="Mora" value="{{ Money::format($overdueInstallmentsAmount) }}" hint="{{ number_format($overdueInstallmentsCount, 0, ',', '.') }} cuota(s) vencida(s)" />
                </div>
            </div>

            <x-card title="Buscar y filtrar" icon="filter" color="secondary">
                <form method="GET" action="{{ route('reports.portfolio') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-lg-7">
                        <label for="search" class="form-label small text-muted">Buscar cliente</label>
                        <input type="search" class="form-control" id="search" name="search" value="{{ $search }}" placeholder="Nombre, teléfono o documento">
                    </div>

                    <div class="col-6 col-lg-3">
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

                        @if ($hasFilters)
                            <a href="{{ route('reports.portfolio') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </x-card>

            <div class="row">
                <div class="col-12 col-xl-6">
                    <x-card title="Distribución por estado" subtitle="Préstamos y saldo pendiente de cada estado." icon="chart-pie" color="primary" class="h-100">
                        @if ($totalLoans > 0)
                            @foreach (LoanStatus::cases() as $case)
                                @php
                                    $row = $byStatus[$case->value] ?? null;
                                    $count = (int) ($row['loans'] ?? 0);
                                    $balance = (float) ($row['balance'] ?? 0);
                                    $percent = $totalLoans > 0 ? (int) round(($count / $totalLoans) * 100) : 0;
                                    $color = ['error' => 'danger', 'neutral' => 'secondary'][$case->color()] ?? $case->color();
                                @endphp

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center small mb-1">
                                        <span class="text-muted">{{ $case->label() }}</span>
                                        <span class="fw-semibold">{{ number_format($count, 0, ',', '.') }} · {{ Money::format($balance) }}</span>
                                    </div>

                                    <div class="progress" style="height: 6px;" role="progressbar" aria-label="{{ $case->label() }}" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar bg-{{ $color }}" style="width: {{ $percent }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <x-empty-state icon="folder-open" title="Sin préstamos" text="No hay préstamos que coincidan con los filtros." />
                        @endif

                        <div class="row g-2 text-center border-top pt-3 mt-3">
                            <div class="col-4">
                                <div class="border rounded-3 p-2 h-100">
                                    <div class="h5 fw-bold mb-0">{{ number_format($totalLoans, 0, ',', '.') }}</div>
                                    <div class="small text-muted">Préstamos</div>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="border rounded-3 p-2 h-100">
                                    <div class="h5 fw-bold mb-0">{{ Money::format($totalPrincipal) }}</div>
                                    <div class="small text-muted">Capital</div>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="border rounded-3 p-2 h-100">
                                    <div class="h5 fw-bold mb-0">{{ Money::format($pendingBalance) }}</div>
                                    <div class="small text-muted">Saldo</div>
                                </div>
                            </div>
                        </div>
                    </x-card>
                </div>

                <div class="col-12 col-xl-6">
                    <x-card title="Clientes con mayor deuda" subtitle="Top 8 por saldo pendiente en cartera." icon="users" color="info" class="h-100">
                        @forelse ($topDebt as $row)
                            @php
                                $client = $row->client;
                            @endphp

                            <a href="{{ $client ? route('clients.show', $client) : '#' }}" class="d-flex align-items-center gap-3 py-2 text-decoration-none text-reset {{ ! $loop->last ? 'border-bottom' : '' }}">
                                <span class="badge text-bg-light border text-muted rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 1.75rem; height: 1.75rem;">
                                    {{ $loop->iteration }}
                                </span>

                                <x-avatar :name="$client?->name ?? 'Cliente'" size="sm" />

                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="fw-semibold text-truncate">{{ $client?->name ?? 'Cliente sin asignar' }}</div>
                                    <div class="small text-muted">{{ number_format((int) $row->loans, 0, ',', '.') }} préstamo(s)</div>
                                </div>

                                <div class="text-end">
                                    <div class="fw-semibold text-nowrap">{{ Money::format((float) $row->debt) }}</div>
                                    <div class="small text-muted">saldo</div>
                                </div>
                            </a>
                        @empty
                            <x-empty-state icon="users" title="Sin deuda pendiente" text="No hay clientes con saldo en cartera." />
                        @endforelse
                    </x-card>
                </div>
            </div>

            <x-card title="Cuotas vencidas recientes" subtitle="Las 10 cuotas vencidas más antiguas de la cartera." icon="alert-triangle" color="danger">
                <x-slot:actions>
                    <a href="{{ route('reports.delinquency') }}" class="btn btn-sm btn-outline-secondary">
                        Ver informe de mora
                    </a>
                </x-slot:actions>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cliente</th>
                                <th>Préstamo</th>
                                <th>Cuota</th>
                                <th>Vencimiento</th>
                                <th class="text-end">Días</th>
                                <th class="text-end">Pendiente</th>
                                <th class="text-end">Mora</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($overdueInstallments as $installment)
                                @php
                                    $client = $installment->loan?->client;
                                @endphp

                                <tr>
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
                                    <td class="text-nowrap">{{ $installment->due_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="text-end text-nowrap">
                                        <span class="badge text-bg-danger">{{ $installment->daysOverdue() }} d</span>
                                    </td>
                                    <td class="text-end text-nowrap fw-semibold">{{ Money::format((float) $installment->pending_balance) }}</td>
                                    <td class="text-end text-nowrap text-danger">{{ Money::format((float) $installment->late_fee) }}</td>
                                    <td><x-status-badge :status="$installment->status" /></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <x-empty-state icon="circle-check" color="success" title="Sin cuotas vencidas" text="La cartera está al día." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

        </div>
    </div>
@endsection
