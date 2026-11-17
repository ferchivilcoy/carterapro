@props([
    'series' => [],
    'color' => 'success',
    'height' => '9rem',
])

@if (count($series) > 0)
    <div {{ $attributes->merge(['class' => 'd-flex align-items-end gap-2']) }} style="height: {{ $height }};">
        @foreach ($series as $month)
            <div class="flex-fill d-flex flex-column h-100">
                <div class="flex-fill d-flex align-items-end">
                    <div
                        class="w-100 rounded-top bg-{{ $color }}{{ $month['is_current'] ? '' : '-lt' }}"
                        style="height: {{ max(3, (int) $month['percent']) }}%"
                        title="{{ $month['label'] }}: {{ \App\Services\Money::format($month['amount']) }}"
                    ></div>
                </div>

                <div class="small text-secondary text-center mt-2">{{ $month['label'] }}</div>

                <span class="visually-hidden">
                    {{ $month['label'] }}: {{ \App\Services\Money::format($month['amount']) }}
                </span>
            </div>
        @endforeach
    </div>
@else
    <x-empty-state
        icon="chart-bar"
        title="Sin ingresos para mostrar"
        text="Todavía no hay cobros registrados en el período."
    />
@endif
