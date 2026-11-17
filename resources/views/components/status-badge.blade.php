@props([
    'status',
    'label' => null,
    'color' => null,
])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;

    $labels = [
        'pending' => 'Pendiente',
        'partial' => 'Parcial',
        'paid' => 'Pagada',
        'overdue' => 'Vencida',
        'cancelled' => 'Anulada',
        'active' => 'Activo',
        'refinanced' => 'Refinanciado',
        'attended' => 'Atendida',
        'no_answer' => 'Sin respuesta',
        'wrong_number' => 'Número equivocado',
        'payment_reported' => 'Pago informado',
    ];

    $colors = [
        'pending' => 'warning',
        'partial' => 'info',
        'paid' => 'success',
        'overdue' => 'danger',
        'cancelled' => 'secondary',
        'active' => 'primary',
        'refinanced' => 'info',
        'attended' => 'success',
        'no_answer' => 'warning',
        'wrong_number' => 'danger',
        'payment_reported' => 'info',
    ];

    $badgeLabel = $label
        ?? (is_object($status) && method_exists($status, 'label') ? $status->label() : ($labels[$value] ?? ucfirst($value)));

    $badgeColor = $color
        ?? (is_object($status) && method_exists($status, 'color') ? $status->color() : ($colors[$value] ?? 'secondary'));

    $badgeColor = match ($badgeColor) {
        'error' => 'danger',
        'neutral' => 'secondary',
        default => $badgeColor,
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge rounded-pill text-bg-'.$badgeColor]) }}>{{ $badgeLabel }}</span>
