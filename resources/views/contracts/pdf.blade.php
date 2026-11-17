@php
    use App\Models\Setting;
    use App\Services\Money;

    $company = Setting::companyName();
    $logo = Setting::companyLogoDataUri();
    $installments = $loan->installments()->orderBy('installment_number')->get();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato {{ $number }}</title>

    <style>
        @page {
            margin: 32px 36px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10.5px;
            color: #1f2937;
            line-height: 1.45;
            margin: 0;
        }

        .header {
            width: 100%;
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }

        .header td {
            vertical-align: middle;
        }

        .brand {
            font-size: 17px;
            font-weight: bold;
            color: #1d4ed8;
        }

        .muted {
            color: #6b7280;
            font-size: 9px;
        }

        .logo {
            max-height: 48px;
            max-width: 140px;
        }

        h1 {
            font-size: 15px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin: 0 0 4px;
        }

        .contract-number {
            text-align: center;
            color: #6b7280;
            font-size: 9.5px;
            margin-bottom: 18px;
        }

        h2 {
            font-size: 10.5px;
            text-transform: uppercase;
            color: #1d4ed8;
            border-bottom: 1px solid #dbeafe;
            padding-bottom: 3px;
            margin: 16px 0 8px;
        }

        p {
            margin: 0 0 8px;
            text-align: justify;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .data td {
            padding: 3px 4px;
            vertical-align: top;
        }

        .data .label {
            color: #6b7280;
            width: 32%;
        }

        .data .value {
            font-weight: bold;
            width: 68%;
        }

        .plan {
            margin-top: 6px;
        }

        .plan th {
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 9px;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #bfdbfe;
            text-align: left;
        }

        .plan td {
            padding: 4px 6px;
            border: 1px solid #e5e7eb;
            font-size: 9.5px;
        }

        .plan .right {
            text-align: right;
        }

        .totals {
            margin-top: 8px;
            width: 55%;
            margin-left: 45%;
        }

        .totals td {
            padding: 3px 6px;
            font-size: 9.5px;
        }

        .totals .right {
            text-align: right;
        }

        .totals .grand td {
            border-top: 1.5px solid #1d4ed8;
            font-weight: bold;
            color: #1d4ed8;
            padding-top: 5px;
        }

        .clause {
            margin-bottom: 7px;
        }

        .clause strong {
            color: #111827;
        }

        .signatures {
            width: 100%;
            margin-top: 42px;
        }

        .signatures td {
            width: 50%;
            text-align: center;
            padding: 0 22px;
            vertical-align: bottom;
        }

        .signature-line {
            border-top: 1px solid #374151;
            padding-top: 5px;
            font-size: 9px;
            color: #4b5563;
        }

        .footer {
            margin-top: 26px;
            border-top: 1px solid #e5e7eb;
            padding-top: 7px;
            font-size: 8px;
            color: #9ca3af;
            text-align: center;
        }

        .avoid {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $company }}" class="logo">
                @else
                    <div class="brand">{{ $company }}</div>
                @endif
            </td>
            <td style="text-align: right;">
                <div class="muted">Contrato de préstamo</div>
                <div class="muted">{{ $number }}</div>
                <div class="muted">Emitido el {{ now()->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <h1>Contrato de mutuo con interés</h1>
    <div class="contract-number">Contrato N.º {{ $number }} · Préstamo N.º {{ $loan->id }}</div>

    <h2>Partes intervinientes</h2>

    <table class="data">
        <tr>
            <td class="label">Acreedor</td>
            <td class="value">{{ $company }}</td>
        </tr>
        <tr>
            <td class="label">Deudor</td>
            <td class="value">{{ $client?->name ?? 'Cliente sin asignar' }}</td>
        </tr>
        <tr>
            <td class="label">Documento</td>
            <td class="value">{{ $client?->document ?: 'No informado' }}</td>
        </tr>
        <tr>
            <td class="label">Teléfono</td>
            <td class="value">{{ $client?->phone ?: 'No informado' }}</td>
        </tr>
        <tr>
            <td class="label">Domicilio</td>
            <td class="value">{{ $client?->address ?: 'No informado' }}</td>
        </tr>
    </table>

    <h2>Condiciones del préstamo</h2>

    <table class="data">
        <tr>
            <td class="label">Capital prestado</td>
            <td class="value">{{ Money::format((float) $loan->principal_amount) }}</td>
        </tr>
        <tr>
            <td class="label">Tasa de interés</td>
            <td class="value">{{ (float) $loan->interest_rate }}% {{ $loan->rate_type?->label() }}</td>
        </tr>
        <tr>
            <td class="label">Modelo de amortización</td>
            <td class="value">{{ $loan->amortization_model?->label() }}</td>
        </tr>
        <tr>
            <td class="label">Cantidad de cuotas</td>
            <td class="value">{{ $loan->installments_count }} cuota(s) {{ $loan->frequency?->label() }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de inicio</td>
            <td class="value">{{ $loan->start_date?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Forma de entrega</td>
            <td class="value">
                {{ $loan->disbursement_method?->label() ?? 'No informada' }}
                @if ($loan->disbursement_account)
                    · {{ $loan->disbursement_account }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Total a devolver</td>
            <td class="value">{{ Money::format((float) $loan->total_amount) }}</td>
        </tr>
    </table>

    <h2>Plan de pagos</h2>

    <table class="plan">
        <thead>
            <tr>
                <th>Cuota</th>
                <th>Vencimiento</th>
                <th class="right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($installments as $installment)
                <tr>
                    <td>#{{ $installment->installment_number }}</td>
                    <td>{{ $installment->due_date?->format('d/m/Y') }}</td>
                    <td class="right">{{ Money::format((float) $installment->expected_amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Capital</td>
            <td class="right">{{ Money::format((float) $loan->principal_amount) }}</td>
        </tr>
        <tr>
            <td>Interés total</td>
            <td class="right">{{ Money::format((float) $loan->total_amount - (float) $loan->principal_amount) }}</td>
        </tr>
        <tr class="grand">
            <td>Total a devolver</td>
            <td class="right">{{ Money::format((float) $loan->total_amount) }}</td>
        </tr>
    </table>

    <div class="avoid">
        <h2>Cláusulas</h2>

        <p class="clause">
            <strong>Primera — Objeto.</strong> El acreedor entrega al deudor la suma de
            {{ Money::format((float) $loan->principal_amount) }} en concepto de mutuo, y el deudor se obliga a
            devolverla junto con los intereses pactados en la forma y plazos establecidos en este contrato.
        </p>

        <p class="clause">
            <strong>Segunda — Plazo y cuotas.</strong> El préstamo se devuelve en {{ $loan->installments_count }}
            cuota(s) con vencimientos {{ $loan->frequency?->label() }}, conforme al plan de pagos detallado
            precedentemente, que forma parte integrante de este contrato.
        </p>

        <p class="clause">
            <strong>Tercera — Intereses.</strong> El deudor abonará un interés del
            {{ (float) $loan->interest_rate }}% {{ $loan->rate_type?->label() }}, calculado según el modelo
            {{ $loan->amortization_model?->label() }}. El total a devolver asciende a
            {{ Money::format((float) $loan->total_amount) }}.
        </p>

        <p class="clause">
            <strong>Cuarta — Mora.</strong> El atraso en el pago de cualquier cuota devengará el interés
            punitorio informado por el acreedor, calculado sobre el saldo vencido, sin perjuicio de las demás
            consecuencias previstas en la normativa aplicable.
        </p>

        <p class="clause">
            <strong>Quinta — Facultad de exigir el saldo.</strong> El incumplimiento de dos o más cuotas
            consecutivas habilita al acreedor a declarar vencido el plazo y exigir el pago íntegro del saldo
            pendiente, con sus intereses y accesorios.
        </p>

        <p class="clause">
            <strong>Sexta — Datos personales.</strong> El deudor autoriza el tratamiento de sus datos personales
            para la administración del préstamo, la cobranza y el cumplimiento de obligaciones legales.
        </p>

        <p class="clause">
            <strong>Séptima — Domicilio.</strong> Las partes constituyen domicilio en los indicados en este
            contrato y se someten a la jurisdicción de los tribunales competentes del domicilio del acreedor.
        </p>

        <p class="clause">
            <strong>Octava — Conformidad.</strong> Las partes leen y aceptan todas y cada una de las cláusulas
            precedentes, firmando dos ejemplares de un mismo tenor y a un solo efecto.
        </p>
    </div>

    <table class="signatures avoid">
        <tr>
            <td>
                <div class="signature-line">
                    {{ $company }}<br>
                    Acreedor
                </div>
            </td>
            <td>
                <div class="signature-line">
                    {{ $client?->name ?? 'Deudor' }}<br>
                    Deudor · DNI {{ $client?->document ?: '—' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $company }} · Contrato {{ $number }} · Documento generado automáticamente por el sistema.
    </div>
</body>
</html>
