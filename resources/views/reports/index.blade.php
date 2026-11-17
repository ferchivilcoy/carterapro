@extends('layouts.app')

@section('content')
    <x-header icon="trending-up" title="Centro de reportes" text="Informes de cartera, mora, caja y cobranzas para la toma de decisiones.">
        <x-slot:actions>
            @can('profitability.view')
                <a href="{{ route('profitability.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-pig-money me-1"></i> Rentabilidad
                </a>
            @endcan
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row">
                <div class="col-12 col-md-6 col-xl-4">
                    <a href="{{ route('reports.executive') }}" class="tile d-block text-decoration-none text-reset h-100">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary-emphasis" style="width: 3rem; height: 3rem;">
                                <i class="ti ti-gauge fs-4"></i>
                            </span>
                            <i class="ti ti-arrow-up-right text-muted"></i>
                        </div>

                        <h2 class="h6 fw-semibold mt-3 mb-1">Tablero ejecutivo</h2>
                        <p class="small text-muted mb-0">
                            Vista consolidada de cartera, mora, cobranzas por cobrador y rentabilidad mensual.
                        </p>
                    </a>
                </div>

                <div class="col-12 col-md-6 col-xl-4">
                    <a href="{{ route('reports.portfolio') }}" class="tile d-block text-decoration-none text-reset h-100">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-info-subtle text-info-emphasis" style="width: 3rem; height: 3rem;">
                                <i class="ti ti-folder-open fs-4"></i>
                            </span>
                            <i class="ti ti-arrow-up-right text-muted"></i>
                        </div>

                        <h2 class="h6 fw-semibold mt-3 mb-1">Estado de cartera</h2>
                        <p class="small text-muted mb-0">
                            Préstamos por estado, capital colocado, saldo pendiente y clientes con mayor deuda.
                        </p>
                    </a>
                </div>

                <div class="col-12 col-md-6 col-xl-4">
                    <a href="{{ route('reports.delinquency') }}" class="tile d-block text-decoration-none text-reset h-100">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-danger-subtle text-danger-emphasis" style="width: 3rem; height: 3rem;">
                                <i class="ti ti-alert-triangle fs-4"></i>
                            </span>
                            <i class="ti ti-arrow-up-right text-muted"></i>
                        </div>

                        <h2 class="h6 fw-semibold mt-3 mb-1">Mora</h2>
                        <p class="small text-muted mb-0">
                            Cuotas vencidas, importe adeudado, mora devengada y distribución por días de atraso.
                        </p>
                    </a>
                </div>

                <div class="col-12 col-md-6 col-xl-4">
                    <a href="{{ route('reports.daily-cash') }}" class="tile d-block text-decoration-none text-reset h-100">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-success-subtle text-success-emphasis" style="width: 3rem; height: 3rem;">
                                <i class="ti ti-cash fs-4"></i>
                            </span>
                            <i class="ti ti-arrow-up-right text-muted"></i>
                        </div>

                        <h2 class="h6 fw-semibold mt-3 mb-1">Caja diaria</h2>
                        <p class="small text-muted mb-0">
                            Cobros efectivos del período, desglose por medio de pago y detalle día por día.
                        </p>
                    </a>
                </div>

                <div class="col-12 col-md-6 col-xl-4">
                    <a href="{{ route('reports.collections-collector') }}" class="tile d-block text-decoration-none text-reset h-100">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-warning-subtle text-warning-emphasis" style="width: 3rem; height: 3rem;">
                                <i class="ti ti-id-badge fs-4"></i>
                            </span>
                            <i class="ti ti-arrow-up-right text-muted"></i>
                        </div>

                        <h2 class="h6 fw-semibold mt-3 mb-1">Cobranzas por cobrador</h2>
                        <p class="small text-muted mb-0">
                            Clientes asignados, cartera, cobros del período y cuotas vencidas por responsable.
                        </p>
                    </a>
                </div>

                @can('profitability.view')
                    <div class="col-12 col-md-6 col-xl-4">
                        <a href="{{ route('profitability.index') }}" class="tile d-block text-decoration-none text-reset h-100">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-secondary-subtle text-secondary-emphasis" style="width: 3rem; height: 3rem;">
                                    <i class="ti ti-pig-money fs-4"></i>
                                </span>
                                <i class="ti ti-arrow-up-right text-muted"></i>
                            </div>

                            <h2 class="h6 fw-semibold mt-3 mb-1">Rentabilidad</h2>
                            <p class="small text-muted mb-0">
                                Capital cobrado, mora, gastos y resultado neto del mes en curso.
                            </p>
                        </a>
                    </div>
                @endcan
            </div>

            <x-card title="Exportaciones" subtitle="Descargas CSV de los listados operativos." icon="download" color="secondary">
                @can('exports.run')
                    <div class="row g-2">
                        <div class="col-12 col-md-4">
                            <a href="{{ route('export.loans') }}" class="btn btn-outline-secondary w-100">
                                <i class="ti ti-file-invoice me-1"></i> Préstamos
                            </a>
                        </div>

                        <div class="col-12 col-md-4">
                            <a href="{{ route('export.payments') }}" class="btn btn-outline-secondary w-100">
                                <i class="ti ti-cash-coin me-1"></i> Pagos
                            </a>
                        </div>

                        <div class="col-12 col-md-4">
                            <a href="{{ route('export.installments') }}" class="btn btn-outline-secondary w-100">
                                <i class="ti ti-calendar-check me-1"></i> Cuotas
                            </a>
                        </div>

                        <div class="col-12 col-md-4">
                            <a href="{{ route('export.clients') }}" class="btn btn-outline-secondary w-100">
                                <i class="ti ti-users me-1"></i> Clientes
                            </a>
                        </div>

                        <div class="col-12 col-md-4">
                            <a href="{{ route('export.cobranzas') }}" class="btn btn-outline-secondary w-100">
                                <i class="ti ti-phone-outgoing me-1"></i> Cobranzas
                            </a>
                        </div>

                        <div class="col-12 col-md-4">
                            <a href="{{ route('export.caja') }}" class="btn btn-outline-secondary w-100">
                                <i class="ti ti-cash-register me-1"></i> Caja
                            </a>
                        </div>

                        <div class="col-12 col-md-4">
                            <a href="{{ route('export.rentabilidad') }}" class="btn btn-outline-secondary w-100">
                                <i class="ti ti-pig-money me-1"></i> Rentabilidad
                            </a>
                        </div>
                    </div>
                @else
                    <p class="small text-muted mb-0">Tu rol no tiene habilitada la exportación de listados.</p>
                @endcan
            </x-card>

        </div>
    </div>
@endsection
