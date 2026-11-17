@props([
    'label',
    'value',
    'icon' => 'chart-bar',
    'color' => 'primary',
    'description' => null,
    'trend' => null,
    'trendLabel' => null,
    'progress' => null,
    'progressLabel' => null,
    'href' => null,
])

@php
    $trendValue = $trend === null ? null : (int) $trend;
    $trendColor = $trendValue === null ? 'secondary' : ($trendValue >= 0 ? 'success' : 'danger');
    $trendIcon = $trendValue === null ? null : ($trendValue >= 0 ? 'trending-up' : 'trending-down');
    $progressValue = $progress === null ? null : max(0, min(100, (int) $progress));
@endphp

<div {{ $attributes->class(['card card-sm h-100 position-relative']) }}>
    @if (filled($href))
        <a href="{{ $href }}" class="stretched-link" aria-label="{{ $label }}"></a>
    @endif

    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-2">
            <span class="avatar avatar-sm bg-{{ $color }}-lt text-{{ $color }} rounded">
                <i class="ti ti-{{ $icon }} fs-3" aria-hidden="true"></i>
            </span>

            @if ($trendValue !== null)
                <span class="badge bg-{{ $trendColor }}-lt text-{{ $trendColor }} d-inline-flex align-items-center gap-1">
                    <i class="ti ti-{{ $trendIcon }} fs-4" aria-hidden="true"></i>
                    {{ $trendValue > 0 ? '+' : '' }}{{ $trendValue }}%
                </span>
            @endif
        </div>

        <div class="h1 mb-0 mt-3">{{ $value }}</div>
        <div class="text-secondary">{{ $label }}</div>

        @if (filled($description))
            <div class="small text-secondary mt-1">{{ $description }}</div>
        @endif

        @if ($trendValue !== null && filled($trendLabel))
            <div class="small text-secondary mt-1">{{ $trendLabel }}</div>
        @endif

        @if ($progressValue !== null)
            <div
                class="progress progress-sm mt-3"
                role="progressbar"
                aria-valuenow="{{ $progressValue }}"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-label="{{ $progressLabel ?? $label }}"
            >
                <div class="progress-bar bg-{{ $color }}" style="width: {{ $progressValue }}%"></div>
            </div>

            @if (filled($progressLabel))
                <div class="small text-secondary mt-1">{{ $progressLabel }}</div>
            @endif
        @endif
    </div>
</div>
