@props([
    'name' => '',
    'size' => 'md',
    'color' => null,
])

@php
    $name = trim((string) $name);

    $initials = collect(preg_split('/\s+/u', $name) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $initials = $initials !== '' ? $initials : '?';

    $palette = ['primary', 'info', 'success', 'warning', 'danger', 'secondary'];
    $avatarColor = $color ?? $palette[abs(crc32($name)) % count($palette)];

    [$dimension, $fontSize] = match ($size) {
        'sm' => ['1.75rem', '.65rem'],
        'lg' => ['2.75rem', '.95rem'],
        default => ['2.25rem', '.75rem'],
    };
@endphp

<span
    {{ $attributes->merge(['class' => 'd-inline-flex align-items-center justify-content-center rounded-circle fw-semibold flex-shrink-0 bg-'.$avatarColor.'-subtle text-'.$avatarColor.'-emphasis']) }}
    style="width: {{ $dimension }}; height: {{ $dimension }}; font-size: {{ $fontSize }};"
    aria-hidden="true"
>{{ $initials }}</span>
