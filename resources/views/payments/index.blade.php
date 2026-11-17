@extends('layouts.app')

@php
    use App\Enums\PaymentMethod;
    use App\Enums\PaymentStatus;
    use App\Services\Money;

    $hasFilters = $search !== '' || $from !== '' || $to !== '' || $method !== 'all' || $status !== 'all';

    $verificationBadge = function (?string $verification): array {
        return match ($verification) {
            'pendiente' => ['label' => 'Pendiente de verificación', 'color' => 'warning'],
            'rechazado' => ['label' => 'Rechazado', 'color' => 'danger'],
            default => ['label' => 'Verificado', 'color' => 'success'],
        };
    };
@endphp

@section('content')
    <x-header icon="cash-coin" title="Pagos" text="Cobros registrados, comprobantes y estado de verificación.">
        <x-slot:actions>
            @can('exports.run')
                <a href="{{ route('export.payments', request()->only(['search', 'from', 'to', 'method', 'status'])) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-download me-1"></i> Exportar
                </a>
            @endcan

            <a href="{{ route('print.payments', request()->only(['search', 'from', 'to', 'method', 'status'])) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-printer me-1"></i> Imprimir
            </a>

            @can('payments.manage')
                <a href="{{ route('payments.create') }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-plus me-1"></i> Registrar pago
                </a>
            @endcan
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-md-4">
                    <x-stat
                        icon="receipt"
                        color="primary"
                        description="Resultados"
                        value="{{ number_format($payments->total(), 0, ',', '.') }}"
                        hint="Pagos en el listado"
                    />
                </div>

                <div class="col-12 col-md-4">
                    <x-stat
                        icon="cash"
                        color="success"
                        description="Cobrado hoy"
                        value="{{ Money::format((float) \App\Models\Payment::active()->today()->sum('amount')) }}"
                        hint="Pagos activos del día"
                    />
                </div>

                <div class="col-12 col-md-4">
                    <x-stat
                        icon="hourglass"
                        color="warning"
                        description="Por verificar"
                        value="{{ number_format(\App\Models\Payment::where('verification_status', 'pendiente')->count(), 0, ',', '.') }}"
                        hint="Con comprobante adjunto"
                    />
                </div>
            </div>

            <x-card title="Buscar y filtrar" icon="filter" color="secondary">
                <form method="GET" action="{{ route('payments.index') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-lg-3">
                        <label for="search" class="form-label small text-muted">Buscar</label>
                        <input type="search" class="form-control" id="search" name="search" value="{{ $search }}" placeholder="Recibo o cliente">
                    </div>

                    <div class="col-6 col-lg-2">
                        <label for="from" class="form-label small text-muted">Desde</label>
                        <input type="date" class="form-control" id="from" name="from" value="{{ $from }}">
                    </div>

                    <div class="col-6 col-lg-2">
                        <label for="to" class="form-label small text-muted">Hasta</label>
                        <input type="date" class="form-control" id="to" name="to" value="{{ $to }}">
                    </div>

                    <div class="col-6 col-lg-2">
                        <label for="method" class="form-label small text-muted">Método</label>
                        <select class="form-select" id="method" name="method">
                            <option value="all">Todos</option>
                            @foreach (PaymentMethod::cases() as $case)
                                <option value="{{ $case->value }}" @selected($method === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-lg-1">
                        <label for="status" class="form-label small text-muted">Estado</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all">Todos</option>
                            @foreach (PaymentStatus::cases() as $case)
                                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ti ti-search me-1"></i> Filtrar
                        </button>

                        @if ($hasFilters)
                            <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </x-card>

            <x-card title="Listado de pagos" subtitle="{{ $payments->total() }} resultado(s)." icon="cash-coin" color="primary">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Recibo</th>
                                <th>Cliente</th>
                                <th>Préstamo / cuota</th>
                                <th>Fecha</th>
                                <th class="text-end">Monto</th>
                                <th class="text-end">Mora</th>
                                <th>Método</th>
                                <th>Verificación</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                @php
                                    $badge = $verificationBadge($payment->verification_status);
                                @endphp

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

                                    <td class="text-nowrap">
                                        <div>Préstamo #{{ $payment->loan_id }}</div>
                                        <div class="small text-muted">
                                            {{ $payment->isPrepayment() ? 'Anticipo a capital' : 'Cuota #'.($payment->installment?->installment_number ?? '—') }}
                                        </div>
                                    </td>

                                    <td class="text-nowrap">{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="text-end text-nowrap fw-semibold">{{ Money::format((float) $payment->amount) }}</td>
                                    <td class="text-end text-nowrap {{ (float) $payment->late_fee_amount > 0 ? 'text-danger' : 'text-muted' }}">
                                        {{ Money::format((float) $payment->late_fee_amount) }}
                                    </td>
                                    <td class="text-nowrap">{{ $payment->method?->label() ?? '—' }}</td>

                                    <td>
                                        <span class="badge rounded-pill text-bg-{{ $badge['color'] }}">{{ $badge['label'] }}</span>
                                    </td>

                                    <td><x-status-badge :status="$payment->status" /></td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="{{ route('payments.show', $payment) }}" class="btn btn-sm btn-outline-secondary" title="Ver pago">
                                                <i class="ti ti-eye"></i>
                                            </a>

                                            <a href="{{ route('print.payments.receipt', $payment) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Imprimir recibo (PDF)">
                                                <i class="ti ti-printer"></i>
                                            </a>

                                            @if ($payment->comprobante_path)
                                                <a href="{{ route('payments.comprobante', $payment) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Ver comprobante">
                                                    <i class="ti ti-paperclip"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">
                                        <x-empty-state
                                            icon="cash-coin"
                                            title="No hay pagos para mostrar"
                                            text="Probá con otros filtros o registrá el primer cobro."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($payments->hasPages())
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-3">
                        <span class="small text-muted">
                            Mostrando {{ $payments->firstItem() }}–{{ $payments->lastItem() }} de {{ $payments->total() }}
                        </span>

                        {{ $payments->links() }}
                    </div>
                @endif
            </x-card>

        </div>
    </div>
@endsection
