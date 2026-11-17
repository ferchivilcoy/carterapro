@props([
    'icon' => 'inbox',
    'title' => 'Sin datos',
    'text' => null,
    'color' => 'secondary',
])

<div {{ $attributes->merge(['class' => 'text-center py-4 px-3']) }}>
    <span
        class="d-inline-flex align-items-center justify-content-center rounded-circle bg-{{ $color }}-lt text-{{ $color }} mb-2"
        style="width: 3rem; height: 3rem;"
        aria-hidden="true"
    >
        @if ($icon instanceof \Illuminate\View\ComponentSlot)
            {{ $icon }}
        @else
            <i class="ti ti-{{ $icon }} fs-2"></i>
        @endif
    </span>

    <p class="fw-semibold mb-1">{{ $title }}</p>

    @if (filled($text))
        <p class="small text-secondary mb-0">{{ $text }}</p>
    @endif
</div>
