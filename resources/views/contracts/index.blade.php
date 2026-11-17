@extends('layouts.app')

@php
    use App\Services\Money;
@endphp

@section('content')
    <x-header icon="file-type-pdf" title="Contratos" text="Contratos de préstamo generados en PDF y disponibles para su descarga.">
        <x-slot:actions>
            <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-file-invoice me-1"></i> Préstamos
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-md-4">
                    <x-stat icon="file-type-pdf" color="primary" description="Contratos" value="{{ number_format($contracts->total(), 0, ',', '.') }}" hint="Generados" />
                </div>

                <div class="col-12 col-md-4">
                    <x-stat icon="file-plus" color="warning" description="Pendientes de contrato" value="{{ number_format($loans->count(), 0, ',', '.') }}" hint="Préstamos sin contrato" />
                </div>

                <div class="col-12 col-md-4">
                    <x-stat icon="calendar" color="info" description="Último contrato" value="{{ $contracts->first()?->generated_at?->format('d/m/Y') ?? '—' }}" hint="Fecha de generación" />
                </div>
            </div>

            @can('contracts.manage')
                <x-card title="Generar contrato" subtitle="Sólo se ofrecen préstamos que todavía no tienen contrato." icon="file-plus" color="success">
                    @if ($loans->isEmpty())
                        <x-empty-state
                            icon="circle-check"
                            color="success"
                            title="Todos los préstamos tienen contrato"
                            text="No hay préstamos pendientes de generar."
                        />
                    @else
                        <form method="POST" action="{{ route('contracts.generate') }}" class="row g-3 align-items-end">
                            @csrf

                            <div class="col-12 col-md-9">
                                <label for="loan_id" class="form-label">Préstamo <span class="text-danger">*</span></label>
                                <select class="form-select @error('loan_id') is-invalid @enderror" id="loan_id" name="loan_id" required>
                                    <option value="">Seleccioná un préstamo</option>
                                    @foreach ($loans as $loan)
                                        <option value="{{ $loan->id }}" @selected((string) old('loan_id') === (string) $loan->id)>
                                            #{{ $loan->id }} · {{ $loan->client?->name ?? 'Cliente sin asignar' }} · {{ Money::format((float) $loan->principal_amount) }} · {{ $loan->start_date?->format('d/m/Y') }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('loan_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-3 d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="ti ti-file-type-pdf me-1"></i> Generar contrato
                                </button>
                            </div>
                        </form>
                    @endif
                </x-card>
            @endcan

            <x-card title="Contratos generados" subtitle="{{ $contracts->total() }} registro(s)." icon="file-type-pdf" color="primary">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Contrato</th>
                                <th>Préstamo</th>
                                <th>Cliente</th>
                                <th class="text-end">Monto</th>
                                <th>Generado</th>
                                <th>Generado por</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contracts as $contract)
                                <tr>
                                    <td class="fw-semibold text-nowrap">{{ $contract->contract_number }}</td>

                                    <td class="text-nowrap">
                                        @if ($contract->loan)
                                            <a href="{{ route('loans.show', $contract->loan) }}" class="text-decoration-none">#{{ $contract->loan_id }}</a>
                                        @else
                                            #{{ $contract->loan_id }}
                                        @endif
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <x-avatar :name="$contract->client?->name ?? 'Cliente'" size="sm" />
                                            <div>
                                                <div class="fw-semibold">{{ $contract->client?->name ?? 'Cliente sin asignar' }}</div>
                                                <div class="small text-muted">{{ $contract->client?->document ? 'DNI '.$contract->client->document : 'Sin documento' }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="text-end text-nowrap">{{ Money::format((float) ($contract->loan?->principal_amount ?? 0)) }}</td>
                                    <td class="text-nowrap small text-muted">{{ $contract->generated_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="text-nowrap small text-muted">{{ $contract->user?->name ?? 'Sistema' }}</td>

                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="{{ route('contracts.show', $contract) }}" class="btn btn-sm btn-outline-secondary" title="Ver contrato">
                                                <i class="ti ti-eye"></i>
                                            </a>

                                            <a href="{{ route('contracts.download', $contract) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Abrir PDF en una pestaña nueva">
                                                <i class="ti ti-file-type-pdf"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <x-empty-state
                                            icon="file-type-pdf"
                                            title="No hay contratos generados"
                                            text="Elegí un préstamo y generá su contrato en PDF."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($contracts->hasPages())
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-3">
                        <span class="small text-muted">
                            Mostrando {{ $contracts->firstItem() }}–{{ $contracts->lastItem() }} de {{ $contracts->total() }}
                        </span>

                        {{ $contracts->links() }}
                    </div>
                @endif
            </x-card>

        </div>
    </div>
@endsection
