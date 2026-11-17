@php
    $title = 'Bitácora de eventos';
    $subtitle = $typeLabel.($search !== '' ? ' · Búsqueda: '.$search : '');
@endphp

@include('prints.partials.styles')
@include('prints.partials.header')

<table class="table">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Cliente</th>
            <th>Préstamo</th>
            <th>Descripción</th>
            <th>Usuario</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($events as $event)
            <tr>
                <td class="nowrap">{{ $event->created_at?->format('d/m/Y H:i') }}</td>
                <td class="nowrap">{{ $event->event_type?->label() ?? '—' }}</td>
                <td>{{ $event->client?->name ?? '—' }}</td>
                <td class="nowrap">{{ $event->loan_id ? '#'.$event->loan_id : '—' }}</td>
                <td>{{ $event->description }}</td>
                <td class="nowrap">{{ $event->creator?->name ?? 'Sistema' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="empty">No hay eventos que coincidan con los filtros.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="totals">
    <tr class="grand">
        <td>Eventos listados</td>
        <td class="right">{{ $events->count() }}</td>
    </tr>
</table>

@include('prints.partials.footer')
