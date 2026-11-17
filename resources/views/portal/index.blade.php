@extends('layouts.app')

@php
    use App\Services\Money;
@endphp

@section('content')
    <x-header icon="home" title="Mi cuenta" text="Resumen de tus préstamos, cuotas y pagos.">
        <x-slot:actions>
            <a href="{{ route('portal.statement') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-file-type-pdf me-1"></i> Estado de cuenta
            </a>

            <a href="{{ route('portal.loans') }}" class="btn btn-primary btn-sm">
                <i class="ti ti-file-invoice me-1"></i> Mis préstamos
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="file-invoice"
                        color="primary"
                        description="Préstamos activos"
                        value="{{ number_format($activeLoansCount, 0, ',', '.') }}"
                        hint="En curso"
                    />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="wallet"
                        color="warning"
                        description="Saldo pendiente"
                        value="{{ Money::format($pendingBalance) }}"
                        hint="A devolver"
                    />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="cash"
                        color="success"
                        description="Total pagado"
                        value="{{ Money::format($totalPaid) }}"
                        hint="Pagos activos"
                    />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="calendar-check"
                        color="info"
                        description="Próximas cuotas"
                        value="{{ $dueInstallments->count() }}"
                        hint="Hasta 5 vencimientos"
                    />
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-xl-7">
                    <x-card title="Próximos vencimientos" icon="calendar-check" color="warning">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Préstamo</th>
                                        <th>Cuota</th>
                                        <th>Vencimiento</th>
                                        <th class="text-end">Saldo</th>
                                        <th>Estado</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($dueInstallments as $installment)
                                        <tr>
                                            <td class="text-nowrap">#{{ $installment->loan_id }}</td>
                                            <td class="text-nowrap">#{{ $installment->installment_number }}</td>
                                            <td class="text-nowrap">{{ $installment->due_date?->format('d/m/Y') }}</td>
                                            <td class="text-end text-nowrap fw-semibold">
                                                {{ Money::format((float) $installment->pending_balance) }}
                                            </td>
                                            <td><x-status-badge :status="$installment->status" /></td>
                                            <td class="text-end">
                                                @if ($installment->loan)
                                                    <a
                                                        href="{{ route('portal.loans.show', $installment->loan) }}"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        title="Ver préstamo"
                                                    >
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">
                                                <x-empty-state
                                                    icon="calendar-check"
                                                    title="Sin cuotas pendientes"
                                                    text="No tenés vencimientos abiertos en este momento."
                                                />
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </div>

                <div class="col-12 col-xl-5">
                    <x-card title="Últimos pagos" icon="cash-coin" color="success">
                        @forelse ($recentPayments as $payment)
                            <div class="d-flex justify-content-between align-items-center gap-2 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                                <div>
                                    <div class="small fw-semibold">{{ $payment->receipt_number }}</div>
                                    <div class="small text-muted">
                                        {{ $payment->payment_date?->format('d/m/Y') }} · Préstamo #{{ $payment->loan_id }}
                                    </div>
                                </div>

                                <div class="text-end">
                                    <div class="small fw-semibold">{{ Money::format((float) $payment->amount) }}</div>
                                    <a
                                        href="{{ route('portal.payments.receipt', $payment) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="small text-decoration-none"
                                    >
                                        <i class="ti ti-download me-1"></i>Recibo
                                    </a>
                                </div>
                            </div>
                        @empty
                            <x-empty-state
                                icon="cash-coin"
                                title="Sin pagos registrados"
                                text="Tus pagos van a aparecer en esta sección."
                            />
                        @endforelse

                        <a href="{{ route('portal.payments') }}" class="btn btn-outline-secondary btn-sm w-100 mt-3">
                            Ver todos mis pagos
                        </a>
                    </x-card>

                    <x-card title="Accesos rápidos" icon="link" color="secondary">
                        <div class="d-grid gap-2">
                            <a href="{{ route('portal.installments') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-calendar-check me-1"></i> Mis cuotas
                            </a>

                            <a href="{{ route('portal.statement') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-file-type-pdf me-1"></i> Descargar estado de cuenta
                            </a>

                            <a href="{{ route('portal.profile') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-user-cog me-1"></i> Mi perfil
                            </a>
                        </div>
                    </x-card>
                </div>
            </div>

        </div>
    </div>
@endsection
