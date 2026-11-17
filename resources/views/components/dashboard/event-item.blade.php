@props([
    'event',
])

@php
    $meta = match ($event->event_type) {
        \App\Enums\EventType::ClientCreated => ['icon' => 'user-plus', 'color' => 'primary'],
        \App\Enums\EventType::ClientUpdated => ['icon' => 'user-cog', 'color' => 'info'],
        \App\Enums\EventType::LoanCreated => ['icon' => 'file-plus', 'color' => 'primary'],
        \App\Enums\EventType::LoanUpdated => ['icon' => 'pencil', 'color' => 'info'],
        \App\Enums\EventType::LoanClosed => ['icon' => 'circle-check', 'color' => 'success'],
        \App\Enums\EventType::LoanCancelled => ['icon' => 'circle-x', 'color' => 'danger'],
        \App\Enums\EventType::LoanRefinanced => ['icon' => 'arrows-exchange', 'color' => 'warning'],
        \App\Enums\EventType::PaymentRegistered => ['icon' => 'cash-banknote', 'color' => 'success'],
        \App\Enums\EventType::PaymentCancelled => ['icon' => 'arrow-back-up', 'color' => 'danger'],
        \App\Enums\EventType::InstallmentOverdue => ['icon' => 'alert-triangle', 'color' => 'danger'],
        \App\Enums\EventType::ReminderGenerated => ['icon' => 'bell', 'color' => 'info'],
        \App\Enums\EventType::RefinanceRequested => ['icon' => 'arrows-left-right', 'color' => 'warning'],
        \App\Enums\EventType::LoginSucceeded => ['icon' => 'login', 'color' => 'secondary'],
        \App\Enums\EventType::LoginFailed => ['icon' => 'shield-exclamation', 'color' => 'danger'],
        \App\Enums\EventType::Logout => ['icon' => 'logout', 'color' => 'secondary'],
        \App\Enums\EventType::ModuleToggled => ['icon' => 'toggle-right', 'color' => 'info'],
        \App\Enums\EventType::ModuleAccessChanged => ['icon' => 'shield-lock', 'color' => 'warning'],
        \App\Enums\EventType::SuperAdminGranted => ['icon' => 'shield-check', 'color' => 'danger'],
        \App\Enums\EventType::SuperAdminRevoked => ['icon' => 'shield-x', 'color' => 'secondary'],
        default => ['icon' => 'activity', 'color' => 'secondary'],
    };
@endphp

<div {{ $attributes->class(['list-group-item']) }}>
    <div class="row align-items-center">
        <div class="col-auto">
            <span class="avatar avatar-sm bg-{{ $meta['color'] }}-lt text-{{ $meta['color'] }} rounded">
                <i class="ti ti-{{ $meta['icon'] }}" aria-hidden="true"></i>
            </span>
        </div>

        <div class="col text-truncate">
            <div class="text-body">{{ $event->description }}</div>

            <div class="d-block text-secondary text-truncate mt-n1 small">
                @if ($event->client)
                    {{ $event->client->name }}
                @endif

                @if ($event->loan)
                    @if ($event->client)
                        ·
                    @endif
                    Préstamo #{{ $event->loan_id }}
                @endif

                @if (! $event->client && ! $event->loan)
                    {{ $event->event_type?->label() ?? 'Evento del sistema' }}
                @endif
            </div>
        </div>

        <div class="col-auto">
            <time
                class="text-secondary small"
                datetime="{{ $event->created_at?->toIso8601String() }}"
                title="{{ $event->created_at?->format('d/m/Y H:i') }}"
            >
                {{ $event->created_at?->diffForHumans() }}
            </time>
        </div>
    </div>
</div>
