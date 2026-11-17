@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'color' => 'primary',
    'status' => null,
    'flush' => false,
])

<div {{ $attributes->class(['card']) }}>
    @if (filled($status))
        <div class="card-status-top bg-{{ $status }}"></div>
    @endif

    @if (filled($title) || filled($subtitle) || isset($actions))
        <div class="card-header">
            <div class="d-flex align-items-center gap-2 min-w-0">
                @if (filled($icon))
                    <span class="avatar avatar-xs bg-{{ $color }}-lt text-{{ $color }} rounded">
                        <i class="ti ti-{{ $icon }} fs-3" aria-hidden="true"></i>
                    </span>
                @endif

                <div class="min-w-0">
                    @if (filled($title))
                        <h3 class="card-title mb-0">{{ $title }}</h3>
                    @endif

                    @if (filled($subtitle))
                        <div class="text-secondary small text-truncate">{{ $subtitle }}</div>
                    @endif
                </div>
            </div>

            @isset($actions)
                <div class="card-actions">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    @if ($flush)
        {{ $slot }}
    @else
        <div class="card-body">
            {{ $slot }}
        </div>
    @endif

    @isset($footer)
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
