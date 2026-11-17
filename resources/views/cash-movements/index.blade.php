@extends('layouts.app')

@php
    use App\Services\Money;

    $hasFilters = $type !== 'all' || $status !== 'all';

    $typeLabel = fn ($case): string => match ($case?->value) {
        'income' => 'Ingreso',
        'expense' => 'Egreso',
        default => '—',
    };

    $typeColor = fn ($case): string => match ($case?->value) {
        'income' => 'success',
        'expense' => 'danger',
        default => 'secondary',
    };
@endphp

@section('content')
    <x-header icon="cash-register" title="Caja" text="Movimientos de ingresos y egresos, saldo del período y cierre diario.">
        <x-slot:actions>
            @can('exports.run')
                <a href="{{ route('export.caja', request()->only(['from', 'to', 'type', 'status'])) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-download me-1"></i> Exportar
                </a>
            @endcan

            @can('cash-movements.manage')
                <form
                    method="POST"
                    action="{{ route('cash-movements.close') }}"
                    data-confirm-delete
                    data-confirm-title="Cerrar caja"
                    data-confirm-text="Se van a cerrar todos los movimientos abiertos. No se pueden reabrir desde la pantalla."
                    data-confirm-button="Sí, cerrar caja"
                    data-confirm-cancel="Cancelar"
                >
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-lock me-1"></i> Cerrar caja ({{ $open }})
                    </button>
                </form>
            @endcan
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="arrow-down-circle" color="success" description="Ingresos" value="{{ Money::format($totalIncomes) }}" hint="Movimientos del período" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="arrow-up-circle" color="danger" description="Egresos" value="{{ Money::format($totalExpenses) }}" hint="Movimientos del período" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="calculator" color="{{ $balance >= 0 ? 'primary' : 'danger' }}" description="Saldo" value="{{ Money::format($balance) }}" hint="Ingresos menos egresos" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="lock-open" color="warning" description="Abiertos" value="{{ number_format($open, 0, ',', '.') }}" hint="Pendientes de cierre" />
                </div>
            </div>

            <x-card title="Buscar y filtrar" icon="filter" color="secondary">
                <form method="GET" action="{{ route('cash-movements.index') }}" class="row g-2 align-items-end">
                    <div class="col-6 col-lg-3">
                        <label for="from" class="form-label small text-muted">Desde</label>
                        <input type="date" class="form-control" id="from" name="from" value="{{ $from }}">
                    </div>

                    <div class="col-6 col-lg-3">
                        <label for="to" class="form-label small text-muted">Hasta</label>
                        <input type="date" class="form-control" id="to" name="to" value="{{ $to }}">
                    </div>

                    <div class="col-6 col-lg-2">
                        <label for="type" class="form-label small text-muted">Tipo</label>
                        <select class="form-select" id="type" name="type">
                            <option value="all">Todos</option>
                            @foreach ($types as $case)
                                <option value="{{ $case->value }}" @selected($type === $case->value)>{{ $typeLabel($case) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-lg-2">
                        <label for="status" class="form-label small text-muted">Estado</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all">Todos</option>
                            <option value="open" @selected($status === 'open')>Abierto</option>
                            <option value="closed" @selected($status === 'closed')>Cerrado</option>
                        </select>
                    </div>

                    <div class="col-12 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="ti ti-search me-1"></i> Filtrar
                        </button>

                        @if ($hasFilters)
                            <a href="{{ route('cash-movements.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                                <i class="ti ti-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </x-card>

            @can('cash-movements.manage')
                <x-card title="Registrar movimiento" subtitle="Los ingresos y egresos manuales se cargan acá." icon="circle-plus" color="success">
                    <form method="POST" action="{{ route('cash-movements.store') }}" class="row g-3 align-items-end">
                        @csrf

                        <div class="col-12 col-md-2">
                            <label for="movement_type" class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select class="form-select @error('type') is-invalid @enderror" id="movement_type" name="type" required>
                                @foreach ($types as $case)
                                    <option value="{{ $case->value }}" @selected(old('type', 'income') === $case->value)>{{ $typeLabel($case) }}</option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-2">
                            <label for="movement_amount" class="form-label">Monto <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" id="movement_amount" name="amount" value="{{ old('amount') }}" required>
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-12 col-md-5">
                            <label for="concept" class="form-label">Concepto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('concept') is-invalid @enderror" id="concept" name="concept" value="{{ old('concept') }}" maxlength="255" required>
                            @error('concept')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-2">
                            <label for="movement_date" class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('date') is-invalid @enderror" id="movement_date" name="date" value="{{ old('date', today()->toDateString()) }}" required>
                            @error('date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-1 d-grid">
                            <button type="submit" class="btn btn-success" title="Guardar movimiento">
                                <i class="ti ti-check"></i>
                            </button>
                        </div>
                    </form>
                </x-card>
            @endcan

            <x-card title="Movimientos" subtitle="{{ $movements->total() }} registro(s) entre {{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }} y {{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }}." icon="cash-register" color="primary">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Concepto</th>
                                <th class="text-end">Monto</th>
                                <th>Responsable</th>
                                <th>Estado</th>
                                <th>Cierre</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($movements as $movement)
                                <tr>
                                    <td class="text-nowrap">{{ $movement->date?->format('d/m/Y') ?? '—' }}</td>

                                    <td>
                                        <span class="badge rounded-pill text-bg-{{ $typeColor($movement->type) }}">
                                            {{ $typeLabel($movement->type) }}
                                        </span>
                                    </td>

                                    <td>{{ $movement->concept }}</td>

                                    <td class="text-end text-nowrap fw-semibold {{ $movement->type?->value === 'income' ? 'text-success' : 'text-danger' }}">
                                        {{ $movement->type?->value === 'income' ? '+' : '−' }}{{ Money::format((float) $movement->amount) }}
                                    </td>

                                    <td class="text-nowrap small text-muted">{{ $movement->user?->name ?? 'Sistema' }}</td>

                                    <td>
                                        <span class="badge rounded-pill {{ $movement->status === 'open' ? 'text-bg-warning' : 'text-bg-secondary' }}">
                                            {{ $movement->status === 'open' ? 'Abierto' : 'Cerrado' }}
                                        </span>
                                    </td>

                                    <td class="text-nowrap small text-muted">{{ $movement->closed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <x-empty-state
                                            icon="cash-register"
                                            title="No hay movimientos para mostrar"
                                            text="Cambiá el período o registrá el primer movimiento de caja."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($movements->hasPages())
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-3">
                        <span class="small text-muted">
                            Mostrando {{ $movements->firstItem() }}–{{ $movements->lastItem() }} de {{ $movements->total() }}
                        </span>

                        {{ $movements->links() }}
                    </div>
                @endif
            </x-card>

        </div>
    </div>
@endsection
