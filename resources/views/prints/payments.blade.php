@php
    use App\Services\Money;

    $range = $from !== '' || $to !== ''
        ? ' · Del '.($from !== '' ? $from : 'inicio').' al '.($to !== '' ? $to : 'hoy')
        : '';

    $title = 'Listado de pagos';
    $subtitle = $methodLabel.' · '.$statusLabel.$range.($search !== '' ? ' · Búsqueda: '.$search : '');

    $totalAmount = (float) $payments->sum('amount');
    $totalLateFee = (float) $payments->sum('late_fee_amount');
@endphp

@include('prints.partials.styles')
@include('prints.partials.header')

<table class="table">
    <thead>
        <tr>
            <th>Recibo</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Préstamo</th>
            <th class="center">Cuota</th>
            <th class="right">Monto</th>
            <th class="right">Mora</th>
            <th>Método</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($payments as $payment)
            <tr>
                <td class="nowrap">{{ $payment->receipt_number }}</td>
                <td class="nowrap">{{ $payment->payment_date?->format('d/m/Y') }}</td>
                <td>{{ $payment->client?->name ?? 'Sin asignar' }}</td>
                <td class="nowrap">#{{ $payment->loan_id }}</td>
                <td class="center nowrap">
                    {{ $payment->isPrepayment() ? 'Anticipo' : ($payment->installment?->installment_number ?? '—') }}
                </td>
                <td class="right nowrap">{{ Money::format((float) $payment->amount) }}</td>
                <td class="right nowrap">{{ Money::format((float) $payment->late_fee_amount) }}</td>
                <td class="nowrap">{{ $payment->method?->label() ?? '—' }}</td>
                <td class="nowrap">{{ $payment->status?->label() ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="empty">No hay pagos que coincidan con los filtros.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="totals">
    <tr>
        <td>Pagos listados</td>
        <td class="right">{{ $payments->count() }}</td>
    </tr>
    <tr>
        <td>Mora incluida</td>
        <td class="right">{{ Money::format($totalLateFee) }}</td>
    </tr>
    <tr class="grand">
        <td>Total cobrado</td>
        <td class="right">{{ Money::format($totalAmount) }}</td>
    </tr>
</table>

@include('prints.partials.footer')
