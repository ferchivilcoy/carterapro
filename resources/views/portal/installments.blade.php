@extends('layouts.app')

@php
    use App\Services\Money;
@endphp

@section('content')
    <x-header icon="calendar-check" title="Mis cuotas" text="Todas las cuotas de tus préstamos, ordenadas por vencimiento.">
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
                title="Listado de cuotas"
                subtitle="{{ $installments->total() }} cuota(s)."
                icon="calendar-check"
                color="primary"
            >
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Préstamo</th>
                                <th>Cuota</th>
                                <th>Vencimiento</th>
                                <th class="text-end">Esperado</th>
                                <th class="text-end">Pagado</th>
                                <th class="text-end">Saldo</th>
                                <th class="text-end">Mora</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($installments as $installment)
                                <tr class="{{ $installment->status->value === 'overdue' ? 'table-danger' : '' }}">
                                    <td class="text-nowrap">
                                        @if ($installment->loan)
                                            <a href="{{ route('portal.loans.show', $installment->loan) }}" class="text-decoration-none">
                                                #{{ $installment->loan_id }}
                                            </a>
                                        @else
                                            #{{ $installment->loan_id }}
                                        @endif
                                    </td>
                                    <td class="text-nowrap">#{{ $installment->installment_number }}</td>
                                    <td class="text-nowrap">{{ $installment->due_date?->format('d/m/Y') }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format((float) $installment->expected_amount) }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format((float) $installment->paid_amount) }}</td>
                                    <td class="text-end text-nowrap fw-semibold">{{ Money::format((float) $installment->pending_balance) }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format((float) $installment->late_fee) }}</td>
                                    <td><x-status-badge :status="$installment->status" /></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <x-empty-state
                                            icon="calendar-check"
                                            title="No hay cuotas para mostrar"
                                            text="Cuando se genere el plan de tu préstamo vas a ver las cuotas acá."
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
