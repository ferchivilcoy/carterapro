@props([
    'items' => [],
    'total' => 0,
])

@php
    $segments = collect($items)->filter(fn (array $item): bool => ($item['count'] ?? 0) > 0);
@endphp

<div {{ $attributes }}>
    @if ($total > 0)
        <div class="d-flex align-items-baseline justify-content-between mb-3">
            <div>
                <div class="h2 mb-0">{{ number_format($total, 0, ',', '.') }}</div>
                <div class="text-secondary">préstamos registrados</div>
            </div>

            <span class="badge bg-primary-lt text-primary">
                {{ $segments->count() }} estado{{ $segments->count() === 1 ? '' : 's' }}
            </span>
        </div>

        <div class="progress-stacked mb-4" style="height: .5rem;">
            @foreach ($segments as $item)
                <div
                    class="progress"
                    style="width: {{ $item['percent'] }}%"
                    role="progressbar"
                    aria-valuenow="{{ $item['percent'] }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="{{ $item['label'] }}: {{ $item['percent'] }}%"
                >
                    <div class="progress-bar bg-{{ $item['color'] }}"></div>
                </div>
            @endforeach
        </div>

        <div class="list-group list-group-flush">
            @foreach ($items as $item)
                <div class="list-group-item px-0 d-flex align-items-center">
                    <span class="status-dot bg-{{ $item['color'] }} me-2"></span>
                    <span class="text-secondary">{{ $item['label'] }}</span>
                    <span class="ms-auto fw-semibold">{{ number_format($item['count'], 0, ',', '.') }}</span>
                    <span class="text-secondary ms-2 small">{{ $item['percent'] }}%</span>
                </div>
            @endforeach
        </div>
    @else
        <x-empty-state
            icon="chart-pie"
            title="Sin préstamos registrados"
            text="Cuando se cargue el primer préstamo, acá verás la distribución por estado."
        />
    @endif
</div>
