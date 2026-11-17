@php
    use App\Models\Setting;

    $company = $company ?? Setting::companyName();
    $logo = $logo ?? Setting::companyLogoDataUri();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Documento' }}</title>

    <style>
        @page {
            margin: 30px 34px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10px;
            color: #1f2937;
            line-height: 1.4;
            margin: 0;
        }

        a {
            color: #1d4ed8;
            text-decoration: none;
        }

        .doc-header {
            width: 100%;
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 9px;
            margin-bottom: 16px;
        }

        .doc-header td {
            vertical-align: middle;
        }

        .doc-logo {
            max-height: 44px;
            max-width: 130px;
        }

        .doc-brand {
            font-size: 16px;
            font-weight: bold;
            color: #1d4ed8;
        }

        .doc-title {
            font-size: 14px;
            font-weight: bold;
            text-align: right;
            color: #111827;
        }

        .doc-meta {
            font-size: 8.5px;
            color: #6b7280;
            text-align: right;
            margin-top: 2px;
        }

        .muted {
            color: #6b7280;
        }

        .small {
            font-size: 8.5px;
        }

        .section-title {
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #1d4ed8;
            border-bottom: 1px solid #dbeafe;
            padding-bottom: 3px;
            margin: 15px 0 7px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .data-table td {
            padding: 3px 4px;
            vertical-align: top;
        }

        .data-table .label {
            width: 26%;
            color: #6b7280;
        }

        .data-table .value {
            width: 74%;
            font-weight: bold;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
        }

        .grid td {
            vertical-align: top;
            padding: 0 10px 0 0;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .table th {
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 8px;
            text-transform: uppercase;
            padding: 5px 5px;
            border: 1px solid #bfdbfe;
            text-align: left;
        }

        .table td {
            padding: 4px 5px;
            border: 1px solid #e5e7eb;
            font-size: 8.7px;
            vertical-align: top;
        }

        .table tbody tr:nth-child(even) td {
            background: #f9fafb;
        }

        .table .right {
            text-align: right;
        }

        .table .center {
            text-align: center;
        }

        .table .nowrap {
            white-space: nowrap;
        }

        .totals {
            width: 52%;
            margin-left: 48%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .totals td {
            padding: 3px 6px;
            font-size: 9px;
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

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 7.5px;
            font-weight: bold;
            color: #ffffff;
            background: #6b7280;
        }

        .badge-success { background: #15803d; }
        .badge-warning { background: #b45309; }
        .badge-danger { background: #b91c1c; }
        .badge-info { background: #0369a1; }
        .badge-secondary { background: #6b7280; }

        .notice {
            border: 1px solid #fde68a;
            background: #fffbeb;
            color: #92400e;
            padding: 7px 9px;
            font-size: 8.5px;
            border-radius: 4px;
            margin-bottom: 8px;
        }

        .empty {
            text-align: center;
            color: #9ca3af;
            font-size: 9px;
            padding: 14px 0;
        }

        .receipt-total {
            border: 2px solid #1d4ed8;
            border-radius: 6px;
            padding: 10px 14px;
            text-align: right;
            margin: 10px 0;
        }

        .receipt-total .label {
            font-size: 8.5px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: .5px;
        }

        .receipt-total .amount {
            font-size: 20px;
            font-weight: bold;
            color: #1d4ed8;
            margin-top: 2px;
        }

        .signature {
            margin-top: 40px;
            width: 100%;
        }

        .signature td {
            width: 50%;
            padding: 0 20px;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-line {
            border-top: 1px solid #374151;
            padding-top: 5px;
            font-size: 8.5px;
            color: #4b5563;
        }

        .doc-footer {
            margin-top: 22px;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            font-size: 7.5px;
            color: #9ca3af;
            text-align: center;
        }

        .avoid {
            page-break-inside: avoid;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
