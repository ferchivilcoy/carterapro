@extends('layouts.app')

@php
    use App\Services\Money;
@endphp

@section('content')
    <x-header icon="cash-coin" title="Mis pagos" text="Historial de pagos registrados y comprobantes descargables.">
        <x-slot:actions>
            <a href="{{ route('portal.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Mi cuenta
            </a>

            <a href="{{ route('portal.statement') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-file-type-pdf me-1"></i> Estado de cuenta
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <x-card
                title="Listado de pagos"
                subtitle="{{ $payments->total() }} pago(s) registrados."
                icon="cash-coin"
                color="success"
            >
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Recibo</th>
                                <th>Fecha</th>
                                <th>Préstamo</th>
                                <th>Cuota</th>
                                <th class="text-end">Monto</th>
                                <th class="text-end">Mora</th>
                                <th>Método</th>
                                <th>Estado</th>
                                <th class="text-end">Comprobante</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td class="fw-semibold text-nowrap">{{ $payment->receipt_number }}</td>
                                    <td class="text-nowrap">{{ $payment->payment_date?->format('d/m/Y') }}</td>
                                    <td class="text-nowrap">
                                        @if ($payment->loan)
                                            <a href="{{ route('portal.loans.show', $payment->loan) }}" class="text-decoration-none">
                                                #{{ $payment->loan_id }}
                                            </a>
                                        @else
                                            #{{ $payment->loan_id }}
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        {{ $payment->isPrepayment() ? 'Anticipo' : '#'.($payment->installment?->installment_number ?? '—') }}
                                    </td>
                                    <td class="text-end text-nowrap fw-semibold">{{ Money::format((float) $payment->amount) }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format((float) $payment->late_fee_amount) }}</td>
                                    <td class="text-nowrap">{{ $payment->method?->label() ?? '—' }}</td>
                                    <td><x-status-badge :status="$payment->status" /></td>
                                    <td class="text-end">
                                        <a
                                            href="{{ route('portal.payments.receipt', $payment) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="btn btn-sm btn-outline-secondary"
                                            title="Descargar recibo"
                                        >
                                            <i class="ti ti-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <x-empty-state
                                            icon="cash-coin"
                                            title="No hay pagos registrados"
                                            text="Cuando se registre un pago vas a poder descargar su comprobante desde acá."
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
