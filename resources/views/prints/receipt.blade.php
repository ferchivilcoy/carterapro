@php
    use App\Services\Money;

    $title = 'Recibo de pago';
    $subtitle = 'Recibo '.$payment->receipt_number;

    $capitalApplied = (float) $payment->amount - (float) $payment->late_fee_amount;
@endphp

@include('prints.partials.styles')
@include('prints.partials.header')

<table class="grid">
    <tr>
        <td style="width: 55%;">
            <div class="section-title" style="margin-top: 0;">Datos del cliente</div>

            <table class="data-table">
                <tr>
                    <td class="label">Nombre</td>
                    <td class="value">{{ $payment->client?->name ?? 'Cliente sin asignar' }}</td>
                </tr>
                <tr>
                    <td class="label">Documento</td>
                    <td class="value">{{ $payment->client?->document ?: 'No informado' }}</td>
                </tr>
                <tr>
                    <td class="label">Teléfono</td>
                    <td class="value">{{ $payment->client?->phone ?: 'No informado' }}</td>
                </tr>
            </table>
        </td>
        <td style="width: 45%;">
            <div class="section-title" style="margin-top: 0;">Datos del recibo</div>

            <table class="data-table">
                <tr>
                    <td class="label">Recibo</td>
                    <td class="value">{{ $payment->receipt_number }}</td>
                </tr>
                <tr>
                    <td class="label">Fecha</td>
                    <td class="value">{{ $payment->payment_date?->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Estado</td>
                    <td class="value">{{ $payment->status?->label() ?? '—' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="section-title">Detalle del pago</div>

<table class="table">
    <thead>
        <tr>
            <th>Concepto</th>
            <th>Préstamo</th>
            <th>Cuota</th>
            <th>Método</th>
            <th class="right">Importe</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $payment->isPrepayment() ? 'Anticipo a capital' : 'Pago de cuota' }}</td>
            <td>#{{ $payment->loan_id }}</td>
            <td>
                {{ $payment->isPrepayment() ? '—' : '#'.($payment->installment?->installment_number ?? '—') }}
            </td>
            <td>{{ $payment->method?->label() ?? '—' }}</td>
            <td class="right">{{ Money::format((float) $payment->amount) }}</td>
        </tr>
    </tbody>
</table>

<table class="totals" style="width: 60%; margin-left: 40%;">
    <tr>
        <td>Aplicado a capital / interés</td>
        <td class="right">{{ Money::format($capitalApplied) }}</td>
    </tr>
    <tr>
        <td>Interés punitorio</td>
        <td class="right">{{ Money::format((float) $payment->late_fee_amount) }}</td>
    </tr>
    <tr class="grand">
        <td>Total abonado</td>
        <td class="right">{{ Money::format((float) $payment->amount) }}</td>
    </tr>
</table>

<div class="receipt-total">
    <div class="label">Total abonado</div>
    <div class="amount">{{ Money::format((float) $payment->amount) }}</div>
</div>

@if ($payment->notes)
    <div class="section-title">Notas</div>
    <p class="small" style="white-space: pre-line;">{{ $payment->notes }}</p>
@endif

<table class="signature avoid">
    <tr>
        <td>
            <div class="signature-line">
                {{ \App\Models\Setting::companyName() }}<br>
                Emisor
            </div>
        </td>
        <td>
            <div class="signature-line">
                {{ $payment->client?->name ?? 'Cliente' }}<br>
                Recibí conforme
            </div>
        </td>
    </tr>
</table>

@include('prints.partials.footer')
