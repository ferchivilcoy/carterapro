@php
    use App\Services\Money;

    $title = 'Plan de pagos';
    $subtitle = 'Préstamo #'.$loan->id.' · '.($loan->client?->name ?? 'Cliente sin asignar');

    $totalExpected = (float) $loan->installments->sum('expected_amount');
    $totalPaid = (float) $loan->installments->sum('paid_amount');
    $totalPending = (float) $loan->installments->sum('pending_balance');
    $totalLateFee = (float) $loan->installments->sum('late_fee');
@endphp

@include('prints.partials.styles')
@include('prints.partials.header')

<table class="grid">
    <tr>
        <td style="width: 50%;">
            <div class="section-title" style="margin-top: 0;">Cliente</div>

            <table class="data-table">
                <tr>
                    <td class="label">Nombre</td>
                    <td class="value">{{ $loan->client?->name ?? 'Sin asignar' }}</td>
                </tr>
                <tr>
                    <td class="label">Documento</td>
                    <td class="value">{{ $loan->client?->document ?: 'No informado' }}</td>
                </tr>
                <tr>
                    <td class="label">Teléfono</td>
                    <td class="value">{{ $loan->client?->phone ?: 'No informado' }}</td>
                </tr>
            </table>
        </td>
        <td style="width: 50%;">
            <div class="section-title" style="margin-top: 0;">Condiciones</div>

            <table class="data-table">
                <tr>
                    <td class="label">Capital</td>
                    <td class="value">{{ Money::format((float) $loan->principal_amount) }}</td>
                </tr>
                <tr>
                    <td class="label">Tasa</td>
                    <td class="value">{{ (float) $loan->interest_rate }}% {{ $loan->rate_type?->label() }}</td>
                </tr>
                <tr>
                    <td class="label">Cuotas</td>
                    <td class="value">{{ $loan->installments_count }} · {{ $loan->frequency?->label() }}</td>
                </tr>
                <tr>
                    <td class="label">Estado</td>
                    <td class="value">{{ $loan->status?->label() ?? '—' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="section-title">Cronograma de cuotas</div>

<table class="table">
    <thead>
        <tr>
            <th class="center">#</th>
            <th>Vencimiento</th>
            <th class="right">Esperado</th>
            <th class="right">Pagado</th>
            <th class="right">Saldo</th>
            <th class="right">Mora</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($loan->installments as $installment)
            <tr>
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
                <td colspan="7" class="empty">El préstamo todavía no tiene cuotas generadas.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="totals">
    <tr>
        <td>Total esperado</td>
        <td class="right">{{ Money::format($totalExpected) }}</td>
    </tr>
    <tr>
        <td>Total pagado</td>
        <td class="right">{{ Money::format($totalPaid) }}</td>
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

<div class="notice">
    El plan de pagos forma parte del contrato de mutuo. El atraso en el pago de una cuota devenga el
    interés punitorio informado por el acreedor sobre el saldo vencido.
</div>

@include('prints.partials.footer')
