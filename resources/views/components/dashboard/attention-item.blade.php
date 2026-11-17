@props([
    'label',
    'value',
    'icon' => 'chevron-right',
    'color' => 'primary',
    'description' => null,
    'href' => '#',
])

<a href="{{ $href }}" {{ $attributes->class(['list-group-item list-group-item-action']) }}>
    <div class="d-flex align-items-center gap-3">
        <span class="avatar avatar-sm bg-{{ $color }}-lt text-{{ $color }} rounded flex-shrink-0">
            <i class="ti ti-{{ $icon }}" aria-hidden="true"></i>
        </span>

        <div class="flex-fill min-w-0">
            <div class="fw-semibold">{{ $label }}</div>

            @if (filled($description))
                <div class="small text-secondary text-truncate">{{ $description }}</div>
            @endif
        </div>

        <span class="badge bg-{{ $color }}-lt text-{{ $color }}">{{ $value }}</span>
        <i class="ti ti-chevron-right text-secondary" aria-hidden="true"></i>
    </div>
</a>
