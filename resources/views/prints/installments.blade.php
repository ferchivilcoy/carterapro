@php
    use App\Services\Money;

    $title = 'Listado de cuotas';
    $subtitle = $statusLabel.' · '.$periodLabel.($search !== '' ? ' · Búsqueda: '.$search : '');

    $totalExpected = (float) $installments->sum('expected_amount');
    $totalPending = (float) $installments->sum('pending_balance');
    $totalLateFee = (float) $installments->sum('late_fee');
@endphp

@include('prints.partials.styles')
@include('prints.partials.header')

<table class="table">
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Préstamo</th>
            <th class="center">Cuota</th>
            <th>Vencimiento</th>
            <th class="right">Esperado</th>
            <th class="right">Pagado</th>
            <th class="right">Saldo</th>
            <th class="right">Mora</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($installments as $installment)
            <tr>
                <td>{{ $installment->loan?->client?->name ?? 'Sin asignar' }}</td>
                <td class="nowrap">#{{ $installment->loan_id }}</td>
                <td class="center nowrap">{{ $installment->installment_number }}</td>
                <td class="nowrap">{{ $installment->due_date?->format('d/m/Y') }}</td>
                <td class="right nowrap">{{ Money::format((float) $installment->expected_amount) }}</td>
                <td class="right nowrap">{{ Money::format((float) $installment->paid_amount) }}</td>
                <td class="right nowrap">{{ Money::format((float) $installment->pending_balance) }}</td>
                <td class="right nowrap">{{ Money::format((float) $installment->late_fee) }}</td>
                <td class="nowrap">{{ $installment->status?->label() ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="empty">No hay cuotas que coincidan con los filtros.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="totals">
    <tr>
        <td>Total esperado ({{ $installments->count() }} cuota/s)</td>
        <td class="right">{{ Money::format($totalExpected) }}</td>
    </tr>
    <tr>
        <td>Mora acumulada</td>
        <td class="right">{{ Money::format($totalLateFee) }}</td>
    </tr>
    <tr class="grand">
        <td>Saldo pendiente</td>
        <td class="right">{{ Money::format($totalPending) }}</td>
    </tr>
</table>

@include('prints.partials.footer')
