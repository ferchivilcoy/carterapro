@php
    use App\Services\Money;

    $title = 'Estado de cuenta';
    $subtitle = 'Cliente #'.$client->id.' · '.($client->document ?: 'Sin documento');

    $loans = $client->loans;
    $payments = $client->payments;

    $totalPrincipal = (float) $loans->sum('principal_amount');
    $totalPending = (float) $loans->sum('pending_balance');
    $totalPaid = (float) $payments->sum('amount');
@endphp

@include('prints.partials.styles')
@include('prints.partials.header')

<div class="section-title">Datos del cliente</div>

<table class="grid">
    <tr>
        <td style="width: 50%;">
            <table class="data-table">
                <tr>
                    <td class="label">Nombre</td>
                    <td class="value">{{ $client->name }}</td>
                </tr>
                <tr>
                    <td class="label">Documento</td>
                    <td class="value">{{ $client->document ?: 'No informado' }}</td>
                </tr>
                <tr>
                    <td class="label">Teléfono</td>
                    <td class="value">{{ $client->phone ?: 'No informado' }}</td>
                </tr>
            </table>
        </td>
        <td style="width: 50%;">
            <table class="data-table">
                <tr>
                    <td class="label">Email</td>
                    <td class="value">{{ $client->email ?: 'No informado' }}</td>
                </tr>
                <tr>
                    <td class="label">Domicilio</td>
                    <td class="value">{{ $client->address ?: 'No informado' }}</td>
                </tr>
                <tr>
                    <td class="label">Estado</td>
                    <td class="value">{{ $client->status?->label() ?? '—' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="totals" style="width: 100%; margin-left: 0; margin-bottom: 4px;">
    <tr>
        <td><span class="muted small">Capital otorgado</span><br><strong>{{ Money::format($totalPrincipal) }}</strong></td>
        <td><span class="muted small">Total cobrado</span><br><strong>{{ Money::format($totalPaid) }}</strong></td>
        <td class="right"><span class="muted small">Saldo pendiente</span><br><strong>{{ Money::format($totalPending) }}</strong></td>
    </tr>
</table>

<div class="section-title">Préstamos</div>

<table class="table">
    <thead>
        <tr>
            <th>Préstamo</th>
            <th>Alta</th>
            <th class="right">Capital</th>
            <th class="right">Total</th>
            <th class="center">Cuotas</th>
            <th class="center">Vencidas</th>
            <th class="right">Saldo</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($loans as $loan)
            <tr>
                <td class="nowrap">#{{ $loan->id }}</td>
                <td class="nowrap">{{ $loan->created_at?->format('d/m/Y') }}</td>
                <td class="right nowrap">{{ Money::format((float) $loan->principal_amount) }}</td>
                <td class="right nowrap">{{ Money::format((float) $loan->total_amount) }}</td>
                <td class="center nowrap">
                    {{ (int) ($loan->paid_installments_count ?? 0) }} / {{ (int) ($loan->installments_count ?? 0) }}
                </td>
                <td class="center nowrap">
                    {{ (int) ($loan->overdue_installments_count ?? 0) }}
                </td>
                <td class="right nowrap">{{ Money::format((float) $loan->pending_balance) }}</td>
                <td class="nowrap">{{ $loan->status?->label() ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="empty">El cliente no tiene préstamos registrados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Pagos activos</div>

<table class="table">
    <thead>
        <tr>
            <th>Recibo</th>
            <th>Fecha</th>
            <th>Préstamo</th>
            <th>Cuota</th>
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
                <td class="nowrap">#{{ $payment->loan_id }}</td>
                <td class="nowrap">
                    {{ $payment->isPrepayment() ? 'Anticipo' : '#'.($payment->installment?->installment_number ?? '—') }}
                </td>
                <td class="right nowrap">{{ Money::format((float) $payment->amount) }}</td>
                <td class="right nowrap">{{ Money::format((float) $payment->late_fee_amount) }}</td>
                <td class="nowrap">{{ $payment->method?->label() ?? '—' }}</td>
                <td class="nowrap">{{ $payment->status?->label() ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="empty">El cliente no tiene pagos activos registrados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="notice">
    Este estado de cuenta incluye únicamente los pagos activos. Los pagos anulados no se descuentan
    del saldo y se conservan en la bitácora del sistema.
</div>

@include('prints.partials.footer')
