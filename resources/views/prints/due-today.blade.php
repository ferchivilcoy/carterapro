@php
    use App\Services\Money;

    $title = 'A cobrar hoy';
    $subtitle = $date?->format('d/m/Y') ?? now()->format('d/m/Y');

    $totalPending = (float) $installments->sum('pending_balance');
    $totalLateFee = (float) $installments->sum('late_fee');
@endphp

@include('prints.partials.styles')
@include('prints.partials.header')

<div class="notice">
    Cuotas con vencimiento del día. Total a cobrar: <strong>{{ Money::format($totalPending + $totalLateFee) }}</strong>
    en {{ $installments->count() }} cuota(s).
</div>

<table class="table">
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Teléfono</th>
            <th>Préstamo</th>
            <th class="center">Cuota</th>
            <th>Vencimiento</th>
            <th class="right">Esperado</th>
            <th class="right">Saldo</th>
            <th class="right">Mora</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($installments as $installment)
            <tr>
                <td>{{ $installment->loan?->client?->name ?? 'Sin asignar' }}</td>
                <td class="nowrap">{{ $installment->loan?->client?->phone ?? '—' }}</td>
                <td class="nowrap">#{{ $installment->loan_id }}</td>
                <td class="center nowrap">{{ $installment->installment_number }}</td>
                <td class="nowrap">{{ $installment->due_date?->format('d/m/Y') }}</td>
                <td class="right nowrap">{{ Money::format((float) $installment->expected_amount) }}</td>
                <td class="right nowrap">{{ Money::format((float) $installment->pending_balance) }}</td>
                <td class="right nowrap">{{ Money::format((float) $installment->late_fee) }}</td>
                <td class="nowrap">{{ $installment->status?->label() ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="empty">No hay cuotas con vencimiento para hoy.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="totals">
    <tr>
        <td>Mora acumulada</td>
        <td class="right">{{ Money::format($totalLateFee) }}</td>
    </tr>
    <tr class="grand">
        <td>Total a cobrar</td>
        <td class="right">{{ Money::format($totalPending + $totalLateFee) }}</td>
    </tr>
</table>

@include('prints.partials.footer')
