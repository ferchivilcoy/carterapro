@extends('layouts.app')

@php
    use App\Services\Money;
@endphp

@section('content')
    <x-header icon="file-invoice" title="Mis préstamos" text="Historial y estado de todos tus préstamos.">
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
                title="Listado de préstamos"
                subtitle="{{ $loans->count() }} préstamo(s) registrados."
                icon="file-invoice"
                color="primary"
            >
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Préstamo</th>
                                <th>Inicio</th>
                                <th class="text-end">Capital</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Cuotas</th>
                                <th class="text-end">Saldo</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($loans as $loan)
                                <tr>
                                    <td class="fw-semibold text-nowrap">#{{ $loan->id }}</td>
                                    <td class="text-nowrap">{{ $loan->start_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format((float) $loan->principal_amount) }}</td>
                                    <td class="text-end text-nowrap">{{ Money::format((float) $loan->total_amount) }}</td>
                                    <td class="text-center text-nowrap">
                                        {{ $loan->paid_installments_count }} / {{ $loan->installments_count }}
                                    </td>
                                    <td class="text-end text-nowrap fw-semibold">{{ Money::format((float) $loan->pending_balance) }}</td>
                                    <td><x-status-badge :status="$loan->status" /></td>
                                    <td class="text-end">
                                        <a
                                            href="{{ route('portal.loans.show', $loan) }}"
                                            class="btn btn-sm btn-outline-secondary"
                                            title="Ver detalle"
                                        >
                                            <i class="ti ti-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <x-empty-state
                                            icon="file-invoice"
                                            title="Todavía no tenés préstamos"
                                            text="Cuando se registre tu primer préstamo va a aparecer acá."
                                        />
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
