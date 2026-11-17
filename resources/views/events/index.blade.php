@extends('layouts.app')

@php
    use App\Enums\EventType;
    use Illuminate\Support\Str;

    $hasFilters = $search !== '' || $type !== 'all';

    $eventMeta = function ($event): array {
        return [
            'client_created' => ['icon' => 'person-plus', 'color' => 'primary'],
            'client_updated' => ['icon' => 'person-gear', 'color' => 'info'],
            'loan_created' => ['icon' => 'file-plus', 'color' => 'primary'],
            'loan_updated' => ['icon' => 'pencil-square', 'color' => 'info'],
            'loan_closed' => ['icon' => 'check2-circle', 'color' => 'success'],
            'loan_cancelled' => ['icon' => 'x-circle', 'color' => 'danger'],
            'loan_refinanced' => ['icon' => 'arrow-repeat', 'color' => 'warning'],
            'payment_registered' => ['icon' => 'cash-coin', 'color' => 'success'],
            'payment_cancelled' => ['icon' => 'arrow-counterclockwise', 'color' => 'danger'],
            'installment_overdue' => ['icon' => 'alert-triangle', 'color' => 'danger'],
            'reminder_generated' => ['icon' => 'bell', 'color' => 'info'],
            'refinance_requested' => ['icon' => 'arrow-left-right', 'color' => 'warning'],
            'user_created' => ['icon' => 'person-badge', 'color' => 'primary'],
            'user_updated' => ['icon' => 'person-gear', 'color' => 'info'],
            'login_succeeded' => ['icon' => 'box-arrow-in-right', 'color' => 'secondary'],
            'login_failed' => ['icon' => 'shield-exclamation', 'color' => 'danger'],
            'logout' => ['icon' => 'box-arrow-right', 'color' => 'secondary'],
            'module_toggled' => ['icon' => 'toggle-right', 'color' => 'info'],
            'module_access_changed' => ['icon' => 'shield-lock', 'color' => 'warning'],
            'super_admin_granted' => ['icon' => 'shield-check', 'color' => 'danger'],
            'super_admin_revoked' => ['icon' => 'shield-x', 'color' => 'secondary'],
        ][$event->event_type?->value] ?? ['icon' => 'activity', 'color' => 'secondary'];
    };
@endphp

@section('content')
    <x-header icon="activity" title="Bitácora" text="Registro cronológico de las operaciones del sistema.">
        <x-slot:actions>
            <a href="{{ route('print.events', request()->only(['search', 'type'])) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-printer me-1"></i> Imprimir
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <x-card title="Buscar y filtrar" icon="filter" color="secondary">
                <form method="GET" action="{{ route('events.index') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-lg-6">
                        <label for="search" class="form-label small text-muted">Buscar por cliente</label>
                        <input type="search" class="form-control" id="search" name="search" value="{{ $search }}" placeholder="Nombre, teléfono o documento">
                    </div>

                    <div class="col-8 col-lg-4">
                        <label for="type" class="form-label small text-muted">Tipo de evento</label>
                        <select class="form-select" id="type" name="type">
                            <option value="all">Todos los tipos</option>
                            @foreach ($types as $case)
                                <option value="{{ $case->value }}" @selected($type === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-4 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ti ti-search me-1"></i> Filtrar
                        </button>

                        @if ($hasFilters)
                            <a href="{{ route('events.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </x-card>

            <x-card title="Últimos eventos" subtitle="{{ $events->total() }} registro(s)." icon="activity" color="primary">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th>Cliente</th>
                                <th>Préstamo</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($events as $event)
                                @php
                                    $meta = $eventMeta($event);
                                @endphp

                                <tr>
                                    <td class="text-nowrap small text-muted">
                                        {{ $event->created_at?->format('d/m/Y') }}
                                        <div>{{ $event->created_at?->format('H:i') }}</div>
                                    </td>

                                    <td class="text-nowrap">
                                        <span class="d-inline-flex align-items-center gap-2">
                                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-{{ $meta['color'] }}-subtle text-{{ $meta['color'] }}-emphasis flex-shrink-0" style="width: 1.75rem; height: 1.75rem;">
                                                <i class="ti ti-{{ $meta['icon'] }}"></i>
                                            </span>
                                            <span class="small fw-semibold">{{ $event->event_type?->label() ?? 'Evento' }}</span>
                                        </span>
                                    </td>

                                    <td class="small">{{ Str::limit($event->description, 160) }}</td>

                                    <td class="text-nowrap">
                                        @if ($event->client)
                                            <a href="{{ route('clients.show', $event->client) }}" class="text-decoration-none">{{ $event->client->name }}</a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-nowrap">
                                        @if ($event->loan)
                                            <a href="{{ route('loans.show', $event->loan) }}" class="text-decoration-none">#{{ $event->loan_id }}</a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-nowrap small text-muted">{{ $event->creator?->name ?? 'Sistema' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <x-empty-state
                                            icon="activity"
                                            title="No hay eventos para mostrar"
                                            text="La bitácora se va a llenar a medida que se registren operaciones."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($events->hasPages())
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-3">
                        <span class="small text-muted">
                            Mostrando {{ $events->firstItem() }}–{{ $events->lastItem() }} de {{ $events->total() }}
                        </span>

                        {{ $events->links() }}
                    </div>
                @endif
            </x-card>

        </div>
    </div>
@endsection
