@php
    use App\Models\Setting;

    $company = Setting::companyName();
    $logo = Setting::companyLogoDataUri();
@endphp

<table class="doc-header">
    <tr>
        <td style="width: 60%;">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $company }}" class="doc-logo">
            @else
                <div class="doc-brand">{{ $company }}</div>
            @endif
        </td>
        <td style="width: 40%;">
            <div class="doc-title">{{ $title ?? 'Documento' }}</div>

            @if (! empty($subtitle))
                <div class="doc-meta">{{ $subtitle }}</div>
            @endif

            <div class="doc-meta">Emitido el {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>
