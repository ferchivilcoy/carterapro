@extends('layouts.app')

@php
    use App\Services\Money;

    $hasFilters = $search !== '' || $result !== '' || $type !== '' || $from !== '' || $to !== '';
@endphp

@section('content')
    <x-header icon="phone-outgoing" title="Cobranzas" text="Gestiones de contacto con clientes, compromisos y pagos informados.">
        <x-slot:actions>
            @can('exports.run')
                <a href="{{ route('export.cobranzas', request()->only(['search', 'result', 'type', 'from', 'to'])) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-download me-1"></i> Exportar
                </a>
            @endcan

            @can('collection-visits.manage')
                <a href="{{ route('collection-visits.create') }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-plus me-1"></i> Nueva gestión
                </a>
            @endcan
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-md-4">
                    <x-stat
                        icon="phone-outgoing"
                        color="primary"
                        description="Gestiones"
                        value="{{ number_format($visits->total(), 0, ',', '.') }}"
                        hint="En el listado"
                    />
                </div>

                <div class="col-12 col-md-4">
                    <x-stat
                        icon="phone-x"
                        color="warning"
                        description="Sin contacto"
                        value="{{ number_format($pendingContact, 0, ',', '.') }}"
                        hint="Sin respuesta o número erróneo"
                    />
                </div>

                <div class="col-12 col-md-4">
                    <x-stat
                        icon="cash-coin"
                        color="success"
                        description="Pagos informados"
                        value="{{ number_format($paymentReported, 0, ',', '.') }}"
                        hint="Para verificar comprobantes"
                    />
                </div>
            </div>

            <x-card title="Buscar y filtrar" icon="filter" color="secondary">
                <form method="GET" action="{{ route('collection-visits.index') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-lg-3">
                        <label for="search" class="form-label small text-muted">Buscar cliente</label>
                        <input type="search" class="form-control" id="search" name="search" value="{{ $search }}" placeholder="Nombre, teléfono o documento">
                    </div>

                    <div class="col-6 col-lg-2">
                        <label for="result" class="form-label small text-muted">Resultado</label>
                        <select class="form-select" id="result" name="result">
                            <option value="">Todos</option>
                            @foreach ($results as $case)
                                <option value="{{ $case->value }}" @selected($result === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-lg-2">
                        <label for="type" class="form-label small text-muted">Canal</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">Todos</option>
                            @foreach ($types as $case)
                                <option value="{{ $case->value }}" @selected($type === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-lg-2">
                        <label for="from" class="form-label small text-muted">Desde</label>
                        <input type="date" class="form-control" id="from" name="from" value="{{ $from }}">
                    </div>

                    <div class="col-6 col-lg-2">
                        <label for="to" class="form-label small text-muted">Hasta</label>
                        <input type="date" class="form-control" id="to" name="to" value="{{ $to }}">
                    </div>

                    <div class="col-12 col-lg-1 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1" title="Filtrar">
                            <i class="ti ti-search"></i>
                        </button>

                        @if ($hasFilters)
                            <a href="{{ route('collection-visits.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </x-card>

            @can('collection-visits.manage')
                <x-card title="Registrar gestión" subtitle="Cargá el resultado del contacto sin salir del listado." icon="circle-plus" color="success">
                    <x-slot:actions>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#new-visit-form" aria-expanded="false" aria-controls="new-visit-form">
                            <i class="ti ti-chevron-down me-1"></i> Mostrar formulario
                        </button>
                    </x-slot:actions>

                    <div class="collapse" id="new-visit-form">
                        <form method="POST" action="{{ route('collection-visits.store') }}">
                            @csrf

                            @include('collection-visits.partials.form', [
                                'clients' => $clients,
                                'loans' => $loans,
                                'installments' => $installments,
                                'pendingPayments' => $pendingPayments,
                                'types' => $types,
                                'results' => $results,
                            ])

                            <div class="d-flex justify-content-end flex-wrap gap-2 border-top pt-3 mt-4">
                                <button type="submit" class="btn btn-success">
                                    <i class="ti ti-check me-1"></i> Guardar gestión
                                </button>
                            </div>
                        </form>
                    </div>
                </x-card>
            @endcan

            <x-card title="Historial de gestiones" subtitle="{{ $visits->total() }} registro(s)." icon="history" color="primary">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Canal</th>
                                <th>Resultado</th>
                                <th>Préstamo / cuota</th>
                                <th>Compromiso</th>
                                <th>Gestión</th>
                                <th>Notas</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($visits as $visit)
                                @php
                                    $whatsapp = $visit->whatsappLink();
                                @endphp

                                <tr>
                                    <td class="text-nowrap small text-muted">
                                        {{ $visit->visited_at?->format('d/m/Y') ?? '—' }}
                                        @if ($visit->visited_at_time)
                                            <div>{{ $visit->visited_at_time->format('H:i') }}</div>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <x-avatar :name="$visit->client?->name ?? 'Cliente'" size="sm" />

                                            <div>
                                                <div class="fw-semibold">{{ $visit->client?->name ?? 'Cliente sin asignar' }}</div>
                                                <div class="small text-muted">{{ $visit->client?->phone ?? 'Sin teléfono' }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge rounded-pill text-bg-{{ $visit->type?->color() ?? 'secondary' }}">
                                            {{ $visit->type?->label() ?? '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge rounded-pill text-bg-{{ $visit->result?->color() ?? 'secondary' }}">
                                            {{ $visit->result?->label() ?? '—' }}
                                        </span>
                                    </td>

                                    <td class="text-nowrap small">
                                        @if ($visit->loan)
                                            <a href="{{ route('loans.show', $visit->loan) }}" class="text-decoration-none">#{{ $visit->loan_id }}</a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif

                                        @if ($visit->installment)
                                            <div class="text-muted">Cuota #{{ $visit->installment->installment_number }}</div>
                                        @endif
                                    </td>

                                    <td class="text-nowrap small">
                                        @if ($visit->commitment_date || $visit->commitment_amount)
                                            <div>{{ $visit->commitment_date?->format('d/m/Y') ?? 'Sin fecha' }}</div>
                                            <div class="fw-semibold">{{ Money::format((float) $visit->commitment_amount) }}</div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-nowrap small text-muted">{{ $visit->user?->name ?? 'Sistema' }}</td>

                                    <td class="small text-muted" style="max-width: 18rem;">
                                        {{ $visit->notes ? \Illuminate\Support\Str::limit($visit->notes, 90) : '—' }}
                                    </td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if ($visit->client)
                                                <a href="{{ route('clients.show', $visit->client) }}" class="btn btn-sm btn-outline-secondary" title="Ver cliente">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                            @endif

                                            @if ($whatsapp)
                                                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Abrir WhatsApp">
                                                    <i class="ti ti-brand-whatsapp"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <x-empty-state
                                            icon="phone-outgoing"
                                            title="No hay gestiones para mostrar"
                                            text="Probá con otros filtros o registrá la primera gestión de cobranza."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($visits->hasPages())
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-3">
                        <span class="small text-muted">
                            Mostrando {{ $visits->firstItem() }}–{{ $visits->lastItem() }} de {{ $visits->total() }}
                        </span>

                        {{ $visits->links() }}
                    </div>
                @endif
            </x-card>

        </div>
    </div>
@endsection
