@php
    use App\Models\Setting;
@endphp

<div class="doc-footer">
    {{ Setting::companyName() }} · {{ $title ?? 'Documento' }} · Generado automáticamente por el sistema el {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
