@props([
    'value' => null,
    'description' => null,
    'hint' => null,
    'href' => null,
    'icon' => null,
    'color' => 'primary',
    'avatar' => 'avatar-square',
])

@php
    // El icono se puede indicar con la prop `icon` (nombre de Tabler Icons),
    // con <x-slot:icon> o como contenido por defecto del componente. Si no se
    // envía ninguno, se usa el icono de dinero de Tabler como respaldo.
    $iconSlot = $icon instanceof \Illuminate\View\ComponentSlot ? $icon : null;
    $iconName = is_string($icon) ? $icon : null;
    $iconContent = $iconSlot ?? $slot;
    $hasCustomIcon = filled($iconName) || $iconContent->hasActualContent();
@endphp

<div {{ $attributes->class(['card', 'card-sm']) }}>
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                <span class="bg-{{ $color }} text-white avatar {{ $avatar }}">
                    @if (filled($iconName))
                        <i class="ti ti-{{ $iconName }} icon" aria-hidden="true"></i>
                    @elseif ($hasCustomIcon)
                        {{ $iconContent }}
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon">
                            <path d="M16.7 8a3 3 0 0 0 -2.7 -2h-4a3 3 0 0 0 0 6h4a3 3 0 0 1 0 6h-4a3 3 0 0 1 -2.7 -2"></path>
                            <path d="M12 3v3m0 12v3"></path>
                        </svg>
                    @endif
                </span>
            </div>

            <div class="col">
                @if (filled($value))
                    <div class="fw-medium">{{ $value }}</div>
                @endif

                @if (filled($description))
                    <div class="text-secondary">{{ $description }}</div>
                @endif

                @if (filled($hint))
                    <div class="text-secondary small">{{ $hint }}</div>
                @endif
            </div>
        </div>
    </div>

    @if (filled($href))
        <a href="{{ $href }}" class="stretched-link" aria-label="{{ $description ?? $value }}"></a>
    @endif
</div>
