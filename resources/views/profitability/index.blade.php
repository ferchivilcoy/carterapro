@extends('layouts.app')

@php
    use App\Services\Money;

    $margin = $totalCollected > 0 ? round(($netProfitability / $totalCollected) * 100, 1) : 0.0;
    $collectionRate = $pendingBalance + $totalCollected > 0
        ? round(($totalCollected / ($pendingBalance + $totalCollected)) * 100, 1)
        : 0.0;
@endphp

@section('content')
    <x-header icon="pig-money" title="Rentabilidad" text="Resultado del mes en curso: cobros, mora, gastos y utilidad neta.">
        <x-slot:actions>
            @can('exports.run')
                <a href="{{ route('export.rentabilidad') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-download me-1"></i> Exportar
                </a>
            @endcan

            @can('reports.view')
                <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-trending-up me-1"></i> Reportes
                </a>
            @endcan
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="cash-banknote"
                        color="primary"
                        description="Capital cobrado"
                        value="{{ Money::format($capitalCollected) }}"
                        hint="Mes en curso"
                    />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="alert-triangle"
                        color="warning"
                        description="Mora cobrada"
                        value="{{ Money::format($lateFeesCollected) }}"
                        hint="Interés punitorio"
                    />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="arrow-up-circle"
                        color="danger"
                        description="Gastos del mes"
                        value="{{ Money::format($expensesThisMonth) }}"
                        hint="Movimientos de caja"
                    />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="trending-up"
                        color="{{ $netProfitability >= 0 ? 'success' : 'danger' }}"
                        description="Rentabilidad neta"
                        value="{{ Money::format($netProfitability) }}"
                        hint="{{ $margin }}% sobre cobros"
                    />
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-xl-8">
                    <x-card title="Composición del resultado" subtitle="Cómo se forma la rentabilidad del mes." icon="calculator" color="primary">
                        <dl class="row small mb-0">
                            <dt class="col-7 text-muted fw-normal">Capital cobrado</dt>
                            <dd class="col-5 text-end">{{ Money::format($capitalCollected) }}</dd>

                            <dt class="col-7 text-muted fw-normal">Mora cobrada</dt>
                            <dd class="col-5 text-end">{{ Money::format($lateFeesCollected) }}</dd>

                            <dt class="col-7 fw-semibold border-top pt-2 mt-2">Total cobrado</dt>
                            <dd class="col-5 text-end fw-semibold border-top pt-2 mt-2">{{ Money::format($totalCollected) }}</dd>

                            <dt class="col-7 text-muted fw-normal">Mora no cobrada (vencida)</dt>
                            <dd class="col-5 text-end text-warning">{{ Money::format($lateFeesUncollected) }}</dd>

                            <dt class="col-7 text-muted fw-normal">Gastos del mes</dt>
                            <dd class="col-5 text-end text-danger">−{{ Money::format($expensesThisMonth) }}</dd>

                            <dt class="col-7 fw-semibold border-top pt-2 mt-2">Rentabilidad neta</dt>
                            <dd class="col-5 text-end fw-bold border-top pt-2 mt-2 {{ $netProfitability >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ Money::format($netProfitability) }}
                            </dd>
                        </dl>

                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <span class="text-muted">Avance de cobranza sobre la cartera</span>
                                <span class="fw-semibold">{{ $collectionRate }}%</span>
                            </div>

                            <div class="progress" style="height: 8px;" role="progressbar" aria-label="Avance de cobranza" aria-valuenow="{{ $collectionRate }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-primary" style="width: {{ min(100, $collectionRate) }}%;"></div>
                            </div>
                        </div>
                    </x-card>

                    <x-card title="Indicadores de cartera" subtitle="Saldo vivo y mora acumulada." icon="wallet" color="info">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted mb-1">Capital en cartera</div>
                                    <div class="h5 fw-bold mb-1">{{ Money::format($totalPrincipal) }}</div>
                                    <div class="small text-muted">{{ number_format($activeLoansCount, 0, ',', '.') }} préstamo(s) activo(s)</div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted mb-1">Saldo pendiente</div>
                                    <div class="h5 fw-bold mb-1">{{ Money::format($pendingBalance) }}</div>
                                    <div class="small text-muted">Capital + interés por cobrar</div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted mb-1">Saldo vencido</div>
                                    <div class="h5 fw-bold mb-1 text-danger">{{ Money::format($overdueBalance) }}</div>
                                    <div class="small text-muted">{{ number_format($overdueCount, 0, ',', '.') }} cuota(s) vencida(s)</div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted mb-1">Mora devengada sin cobrar</div>
                                    <div class="h5 fw-bold mb-1 text-warning">{{ Money::format($lateFeesUncollected) }}</div>
                                    <div class="small text-muted">Interés punitorio acumulado</div>
                                </div>
                            </div>
                        </div>
                    </x-card>
                </div>

                <div class="col-12 col-xl-4">
                    <x-card title="Cómo se calcula" icon="bulb" color="warning">
                        <ul class="small text-muted mb-0 ps-3">
                            <li class="mb-2">
                                <strong class="text-body">Capital cobrado:</strong> suma de los pagos activos del mes menos la mora incluida en cada recibo.
                            </li>
                            <li class="mb-2">
                                <strong class="text-body">Mora cobrada:</strong> la porción de los pagos que se aplicó a interés punitorio.
                            </li>
                            <li class="mb-2">
                                <strong class="text-body">Rentabilidad neta:</strong> mora cobrada menos mora devengada no cobrada y gastos de caja del mes.
                            </li>
                            <li>
                                Los préstamos refinanciados y anulados no forman parte de la cartera activa.
                            </li>
                        </ul>
                    </x-card>

                    <x-card title="Accesos rápidos" icon="link" color="secondary">
                        <div class="d-grid gap-2">
                            <a href="{{ route('reports.daily-cash') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-cash me-1"></i> Caja diaria
                            </a>

                            <a href="{{ route('reports.delinquency') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-alert-triangle me-1"></i> Informe de mora
                            </a>

                            <a href="{{ route('reports.executive') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-gauge me-1"></i> Tablero ejecutivo
                            </a>

                            @can('cash-movements.view')
                                <a href="{{ route('cash-movements.index') }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="ti ti-cash-register me-1"></i> Movimientos de caja
                                </a>
                            @endcan
                        </div>
                    </x-card>
                </div>
            </div>

        </div>
    </div>
@endsection
