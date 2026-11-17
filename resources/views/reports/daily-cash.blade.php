@extends('layouts.app')

@php
    use App\Enums\PaymentMethod;
    use App\Services\Money;

    $hasFilters = $method !== 'all' || $from !== now()->startOfMonth()->toDateString() || $to !== now()->toDateString();
    $average = $count > 0 ? $total / $count : 0.0;
@endphp

@section('content')
    <x-header icon="cash" title="Caja diaria" text="Cobros efectivos del período, medios de pago y detalle día por día.">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Reportes
            </a>

            @can('exports.run')
                <a href="{{ route('export.caja', request()->only(['from', 'to', 'method'])) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-download me-1"></i> Exportar
                </a>
            @endcan
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="receipt" color="primary" description="Cobros" value="{{ number_format($count, 0, ',', '.') }}" hint="Recibos activos" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="cash-coin" color="success" description="Total cobrado" value="{{ Money::format($total) }}" hint="Incluye mora" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="alert-triangle" color="warning" description="Mora incluida" value="{{ Money::format($lateFees) }}" hint="Interés punitorio" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="calculator" color="info" description="Promedio por cobro" value="{{ Money::format($average) }}" hint="Total / cantidad" />
                </div>
            </div>

            <x-card title="Filtrar el período" icon="filter" color="secondary">
                <form method="GET" action="{{ route('reports.daily-cash') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label for="from" class="form-label small text-muted">Desde</label>
                        <input type="date" class="form-control" id="from" name="from" value="{{ $from }}">
                    </div>

                    <div class="col-12 col-md-3">
                        <label for="to" class="form-label small text-muted">Hasta</label>
                        <input type="date" class="form-control" id="to" name="to" value="{{ $to }}">
                    </div>

                    <div class="col-12 col-md-3">
                        <label for="method" class="form-label small text-muted">Método</label>
                        <select class="form-select" id="method" name="method">
                            <option value="all">Todos</option>
                            @foreach ($methods as $case)
                                <option value="{{ $case->value }}" @selected($method === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ti ti-search me-1"></i> Filtrar
                        </button>

                        @if ($hasFilters)
                            <a href="{{ route('reports.daily-cash') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </x-card>

            <div class="row">
                <div class="col-12 col-xl-5">
                    <x-card title="Por medio de pago" subtitle="Distribución de los cobros del período." icon="credit-card" color="primary" class="h-100">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Método</th>
                                        <th class="text-end">Cobros</th>
                                        <th class="text-end">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($byMethod as $row)
                                        <tr>
                                            <td class="fw-semibold">{{ $row['label'] }}</td>
                                            <td class="text-end">{{ number_format($row['count'], 0, ',', '.') }}</td>
                                            <td class="text-end text-nowrap">{{ Money::format($row['amount']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3">
                                                <x-empty-state icon="credit-card" title="Sin cobros" text="No hay pagos activos en el período." />
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if ($byMethod !== [])
                                    <tfoot class="table-light">
                                        <tr>
                                            <th>Total</th>
                                            <th class="text-end">{{ number_format($count, 0, ',', '.') }}</th>
                                            <th class="text-end">{{ Money::format($total) }}</th>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </x-card>
                </div>

                <div class="col-12 col-xl-7">
                    <x-card title="Detalle por día" subtitle="Cobros agrupados por fecha de pago." icon="calendar" color="success" class="h-100">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Día</th>
                                        <th class="text-end">Cobros</th>
                                        <th class="text-end">Monto</th>
                                        <th class="text-end">Mora incluida</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($byDay as $row)
                                        <tr>
                                            <td class="text-nowrap">{{ $row['day'] }}</td>
                                            <td class="text-end">{{ number_format($row['count'], 0, ',', '.') }}</td>
                                            <td class="text-end text-nowrap fw-semibold">{{ Money::format($row['amount']) }}</td>
                                            <td class="text-end text-nowrap text-warning">{{ Money::format($row['lateFees']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">
                                                <x-empty-state icon="calendar" title="Sin movimientos" text="No hay cobros registrados en el período." />
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </div>
            </div>

            <x-card title="Pagos del período" subtitle="{{ number_format($count, 0, ',', '.') }} recibo(s)." icon="receipt" color="primary">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Recibo</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Préstamo / cuota</th>
                                <th>Método</th>
                                <th class="text-end">Monto</th>
                                <th class="text-end">Mora</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td class="fw-semibold text-nowrap">{{ $payment->receipt_number }}</td>

                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <x-avatar :name="$payment->client?->name ?? 'Cliente'" size="sm" />
                                            <div>
                                                <div class="fw-semibold">{{ $payment->client?->name ?? 'Cliente sin asignar' }}</div>
                                                <div class="small text-muted">{{ $payment->client?->phone ?? 'Sin teléfono' }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="text-nowrap">{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</td>

                                    <td class="text-nowrap">
                                        <div>Préstamo #{{ $payment->loan_id }}</div>
                                        <div class="small text-muted">{{ $payment->isPrepayment() ? 'Anticipo' : 'Cuota #'.($payment->installment?->installment_number ?? '—') }}</div>
                                    </td>

                                    <td class="text-nowrap">{{ $payment->method?->label() ?? '—' }}</td>
                                    <td class="text-end text-nowrap fw-semibold">{{ Money::format((float) $payment->amount) }}</td>
                                    <td class="text-end text-nowrap text-warning">{{ Money::format((float) $payment->late_fee_amount) }}</td>
                                    <td><x-status-badge :status="$payment->status" /></td>

                                    <td class="text-end">
                                        @can('payments.view')
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="{{ route('payments.show', $payment) }}" class="btn btn-sm btn-outline-secondary" title="Ver pago">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                                <a href="{{ route('print.payments.receipt', $payment) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Imprimir recibo (PDF)">
                                                    <i class="ti ti-printer"></i>
                                                </a>
                                            </div>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <x-empty-state icon="receipt" title="No hay pagos para mostrar" text="Probá con otro período o método de pago." />
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
