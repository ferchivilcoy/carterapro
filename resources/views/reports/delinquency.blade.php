@extends('layouts.app')

@php
    use App\Services\Money;

    $hasFilters = $from !== '' || $to !== '';
@endphp

@section('content')
    <x-header icon="alert-triangle" title="Informe de mora" text="Cuotas vencidas, importe adeudado y distribución por días de atraso.">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Reportes
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-md-4">
                    <x-stat icon="calendar-x" color="danger" description="Cuotas vencidas" value="{{ number_format($count, 0, ',', '.') }}" hint="Según el período" />
                </div>

                <div class="col-12 col-md-4">
                    <x-stat icon="wallet" color="warning" description="Saldo vencido" value="{{ Money::format($amount) }}" hint="Capital + interés" />
                </div>

                <div class="col-12 col-md-4">
                    <x-stat icon="alert-octagon" color="secondary" description="Mora devengada" value="{{ Money::format($lateFees) }}" hint="Interés punitorio" />
                </div>
            </div>

            <x-card title="Filtrar por vencimiento" icon="filter" color="secondary">
                <form method="GET" action="{{ route('reports.delinquency') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label for="from" class="form-label small text-muted">Vencidas desde</label>
                        <input type="date" class="form-control" id="from" name="from" value="{{ $from }}">
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="to" class="form-label small text-muted">Vencidas hasta</label>
                        <input type="date" class="form-control" id="to" name="to" value="{{ $to }}">
                    </div>

                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ti ti-search me-1"></i> Filtrar
                        </button>

                        @if ($hasFilters)
                            <a href="{{ route('reports.delinquency') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </x-card>

            <x-card title="Distribución por días de atraso" subtitle="Cuánto de la mora se concentra en cada tramo." icon="chart-bar" color="danger">
                <div class="row g-3">
                    @foreach ($distribution as $key => $bucket)
                        @php
                            $bucketCount = (int) $bucket['count'];
                            $percent = $count > 0 ? (int) round(($bucketCount / $count) * 100) : 0;
                            $color = match ($key) {
                                '1-30' => 'info',
                                '31-60' => 'warning',
                                '61-90' => 'danger',
                                default => 'dark',
                            };
                        @endphp

                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <span class="small text-muted">{{ $bucket['label'] }}</span>
                                    <span class="badge text-bg-light border text-muted">{{ $percent }}%</span>
                                </div>

                                <div class="h4 fw-bold mt-2 mb-1">{{ number_format($bucketCount, 0, ',', '.') }}</div>
                                <div class="small text-muted mb-3">{{ Money::format((float) $bucket['amount']) }}</div>

                                <div class="progress" style="height: 6px;" role="progressbar" aria-label="{{ $bucket['label'] }}" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-{{ $color }}" style="width: {{ $percent }}%;"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card title="Detalle de cuotas vencidas" subtitle="{{ number_format($count, 0, ',', '.') }} registro(s)." icon="calendar-x" color="primary">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cliente</th>
                                <th>Préstamo</th>
                                <th>Cuota</th>
                                <th>Vencimiento</th>
                                <th class="text-end">Días</th>
                                <th class="text-end">Esperado</th>
                                <th class="text-end">Pagado</th>
                                <th class="text-end">Pendiente</th>
                                <th class="text-end">Mora</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($installments as $installment)
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
                                    <td class="text-end text-nowrap">{{ Money::format((float) $installment->expected_amount) }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format((float) $installment->paid_amount) }}</td>
                                    <td class="text-end text-nowrap fw-semibold">{{ Money::format((float) $installment->pending_balance) }}</td>
                                    <td class="text-end text-nowrap text-danger">{{ Money::format((float) $installment->late_fee) }}</td>
                                    <td><x-status-badge :status="$installment->status" /></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">
                                        <x-empty-state icon="circle-check" color="success" title="No hay cuotas vencidas" text="Probá con otro período o la cartera está al día." />
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
