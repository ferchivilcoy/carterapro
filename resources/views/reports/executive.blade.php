@extends('layouts.app')

@php
    use App\Enums\LoanStatus;
    use App\Services\Money;

    $hasFilters = $from !== now()->startOfMonth()->toDateString() || $to !== now()->toDateString();
    $totalMora = array_sum(array_column($moraByRange, 'amount'));
    $maxMora = max([1, ...array_column($moraByRange, 'amount')]);
    $maxCollector = max([1, ...array_column($collectorRows, 'collectedTotal')]);
    $totalCollectedRows = array_sum(array_column($collectorRows, 'collectedTotal'));
@endphp

@section('content')
    <x-header icon="gauge" title="Tablero ejecutivo" text="Visión consolidada de cartera, mora, cobranzas y rentabilidad.">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Reportes
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <x-card title="Período" icon="calendar" color="secondary">
                <form method="GET" action="{{ route('reports.executive') }}" class="row g-2 align-items-end">
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
                            <i class="ti ti-search me-1"></i> Aplicar
                        </button>

                        @if ($hasFilters)
                            <a href="{{ route('reports.executive') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </x-card>

            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="file-invoice" color="primary" description="Cartera total" value="{{ number_format($totalLoans, 0, ',', '.') }}" hint="{{ number_format($activeLoans, 0, ',', '.') }} activo(s)" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="cash-banknote" color="info" description="Capital colocado" value="{{ Money::format($totalPrincipal) }}" hint="Histórico" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="wallet" color="warning" description="Saldo pendiente" value="{{ Money::format($pendingBalance) }}" hint="Por cobrar" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="cash-coin" color="success" description="Cobrado del mes" value="{{ Money::format($totalCollectedMTD) }}" hint="Capital + mora" />
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="alert-triangle" color="danger" description="Mora vencida" value="{{ Money::format($overdueInstallmentsAmount) }}" hint="{{ number_format($overdueInstallmentsCount, 0, ',', '.') }} cuota(s)" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="alert-octagon" color="secondary" description="Mora sin cobrar" value="{{ Money::format($lateFeesUncollected) }}" hint="Devengada" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="arrow-up-circle" color="danger" description="Gastos del mes" value="{{ Money::format($expensesMTD) }}" hint="Caja" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="trending-up" color="{{ $netProfitabilityMTD >= 0 ? 'success' : 'danger' }}" description="Rentabilidad neta" value="{{ Money::format($netProfitabilityMTD) }}" hint="Mora − mora no cobrada − gastos" />
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-xl-6">
                    <x-card title="Cartera por estado" subtitle="Distribución de préstamos y saldo." icon="chart-pie" color="primary" class="h-100">
                        @if ($totalLoans > 0)
                            @foreach (LoanStatus::cases() as $case)
                                @php
                                    $row = $loansByStatus[$case->value] ?? null;
                                    $count = (int) ($row['loans'] ?? 0);
                                    $balance = (float) ($row['balance'] ?? 0);
                                    $percent = (int) round(($count / $totalLoans) * 100);
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
                            <x-empty-state icon="folder-open" title="Sin préstamos" text="Todavía no hay cartera registrada." />
                        @endif
                    </x-card>
                </div>

                <div class="col-12 col-xl-6">
                    <x-card title="Mora por rango de días" subtitle="Concentración del saldo vencido." icon="chart-bar" color="danger" class="h-100">
                        @foreach ($moraByRange as $row)
                            @php
                                $percent = $maxMora > 0 ? (int) round(($row['amount'] / $maxMora) * 100) : 0;
                                $color = str_replace('#', '', $row['color']);
                            @endphp

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center small mb-1">
                                    <span class="text-muted">{{ $row['label'] }}</span>
                                    <span class="fw-semibold">{{ number_format($row['count'], 0, ',', '.') }} cuota(s) · {{ Money::format($row['amount']) }}</span>
                                </div>

                                <div class="progress" style="height: 6px;" role="progressbar" aria-label="{{ $row['label'] }}" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: {{ $percent }}%; background-color: {{ $row['color'] }};"></div>
                                </div>

                                <div class="small text-muted mt-1">Mora devengada: {{ Money::format($row['lateFees']) }}</div>
                            </div>
                        @endforeach

                        <div class="d-flex justify-content-between border-top pt-3 mt-2">
                            <span class="small text-muted">Saldo vencido total</span>
                            <span class="fw-semibold text-danger">{{ Money::format($totalMora) }}</span>
                        </div>
                    </x-card>
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-xl-6">
                    <x-card title="Cobranzas por cobrador" subtitle="Período {{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }}." icon="id-badge" color="success" class="h-100">
                        @forelse ($collectorRows as $row)
                            @php
                                $percent = $maxCollector > 0 ? (int) round(($row['collectedTotal'] / $maxCollector) * 100) : 0;
                            @endphp

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center small mb-1">
                                    <span class="text-muted">{{ $row['name'] }}</span>
                                    <span class="fw-semibold">{{ Money::format($row['collectedTotal']) }}</span>
                                </div>

                                <div class="progress" style="height: 6px;" role="progressbar" aria-label="{{ $row['name'] }}" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-success" style="width: {{ $percent }}%;"></div>
                                </div>

                                <div class="small text-muted mt-1">
                                    {{ number_format($row['clients'], 0, ',', '.') }} cliente(s) · {{ number_format($row['overdueCount'], 0, ',', '.') }} vencida(s)
                                </div>
                            </div>
                        @empty
                            <x-empty-state icon="id-badge" title="Sin cobradores" text="No hay cobradores activos para el período." />
                        @endforelse

                        @if ($collectorRows !== [])
                            <div class="d-flex justify-content-between border-top pt-3 mt-2">
                                <span class="small text-muted">Total cobrado</span>
                                <span class="fw-semibold text-success">{{ Money::format($totalCollectedRows) }}</span>
                            </div>
                        @endif
                    </x-card>
                </div>

                <div class="col-12 col-xl-6">
                    <x-card title="Rentabilidad mensual" subtitle="Últimos 6 meses." icon="trending-up" color="info" class="h-100">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Mes</th>
                                        <th class="text-end">Capital</th>
                                        <th class="text-end">Mora</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Gastos</th>
                                        <th class="text-end">Neta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($monthlyProfitability as $row)
                                        <tr>
                                            <td class="text-nowrap fw-semibold">{{ $row['month'] }}</td>
                                            <td class="text-end text-nowrap">{{ Money::format($row['capitalCollected']) }}</td>
                                            <td class="text-end text-nowrap">{{ Money::format($row['lateFeesCollected']) }}</td>
                                            <td class="text-end text-nowrap">{{ Money::format($row['totalCollected']) }}</td>
                                            <td class="text-end text-nowrap text-danger">{{ Money::format($row['expenses']) }}</td>
                                            <td class="text-end text-nowrap fw-semibold {{ $row['netProfitability'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ Money::format($row['netProfitability']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </div>
            </div>

            <x-card title="Resumen del mes" subtitle="Indicadores del mes en curso, independientes del período elegido." icon="clipboard-data" color="secondary">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted mb-1">Capital cobrado</div>
                            <div class="h5 fw-bold mb-0">{{ Money::format($capitalCollectedMTD) }}</div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted mb-1">Mora cobrada</div>
                            <div class="h5 fw-bold mb-0">{{ Money::format($lateFeesCollectedMTD) }}</div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted mb-1">Mora no cobrada</div>
                            <div class="h5 fw-bold mb-0 text-warning">{{ Money::format($lateFeesUncollected) }}</div>
                        </div>
                    </div>
                </div>
            </x-card>

        </div>
    </div>
@endsection
