@extends('layouts.app')

@php
    use App\Services\Money;
@endphp

@section('content')
    <x-header icon="file-type-pdf" title="Contrato {{ $contract->contract_number }}" text="{{ $contract->client?->name }} · Préstamo #{{ $contract->loan_id }}">
        <x-slot:actions>
            <a href="{{ route('contracts.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver
            </a>

            <a href="{{ route('contracts.download', $contract) }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                <i class="ti ti-file-type-pdf me-1"></i> Abrir PDF
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row">
                <div class="col-12 col-xl-8">
                    <x-card title="Datos del contrato" icon="file-invoice" color="primary">
                        <dl class="row small mb-0">
                            <dt class="col-5 col-md-4 text-muted fw-normal">Número</dt>
                            <dd class="col-7 col-md-8 fw-semibold">{{ $contract->contract_number }}</dd>

                            <dt class="col-5 col-md-4 text-muted fw-normal">Cliente</dt>
                            <dd class="col-7 col-md-8">
                                @if ($contract->client)
                                    <a href="{{ route('clients.show', $contract->client) }}" class="text-decoration-none">{{ $contract->client->name }}</a>
                                    <div class="small text-muted">
                                        {{ $contract->client->document ? 'DNI '.$contract->client->document : 'Sin documento' }}
                                        · {{ $contract->client->phone }}
                                    </div>
                                @else
                                    —
                                @endif
                            </dd>

                            <dt class="col-5 col-md-4 text-muted fw-normal">Préstamo</dt>
                            <dd class="col-7 col-md-8">
                                @if ($contract->loan)
                                    <a href="{{ route('loans.show', $contract->loan) }}" class="text-decoration-none">#{{ $contract->loan_id }}</a>
                                @else
                                    #{{ $contract->loan_id }}
                                @endif
                            </dd>

                            @if ($contract->loan)
                                <dt class="col-5 col-md-4 text-muted fw-normal">Capital</dt>
                                <dd class="col-7 col-md-8">{{ Money::format((float) $contract->loan->principal_amount) }}</dd>

                                <dt class="col-5 col-md-4 text-muted fw-normal">Total a devolver</dt>
                                <dd class="col-7 col-md-8">{{ Money::format((float) $contract->loan->total_amount) }}</dd>

                                <dt class="col-5 col-md-4 text-muted fw-normal">Plan</dt>
                                <dd class="col-7 col-md-8">
                                    {{ $contract->loan->installments_count }} cuota(s) {{ $contract->loan->frequency?->label() }}
                                    · {{ $contract->loan->interest_rate }}% {{ $contract->loan->rate_type?->label() }}
                                </dd>
                            @endif

                            <dt class="col-5 col-md-4 text-muted fw-normal">Generado</dt>
                            <dd class="col-7 col-md-8">{{ $contract->generated_at?->format('d/m/Y H:i') ?? '—' }}</dd>

                            <dt class="col-5 col-md-4 text-muted fw-normal">Generado por</dt>
                            <dd class="col-7 col-md-8">{{ $contract->user?->name ?? 'Sistema' }}</dd>

                            <dt class="col-5 col-md-4 text-muted fw-normal">Archivo</dt>
                            <dd class="col-7 col-md-8 text-break small text-muted">{{ $contract->pdf_path }}</dd>
                        </dl>
                    </x-card>
                </div>

                <div class="col-12 col-xl-4">
                    <x-card title="Documento" icon="file-type-pdf" color="danger">
                        <div class="text-center py-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger-emphasis mb-3" style="width: 4rem; height: 4rem;">
                                <i class="ti ti-file-type-pdf fs-2"></i>
                            </span>

                            <p class="small text-muted">
                                El PDF se abre en una pestaña nueva y queda disponible mientras el archivo exista en el disco privado.
                            </p>

                            <a href="{{ route('contracts.download', $contract) }}" target="_blank" rel="noopener" class="btn btn-danger w-100">
                                <i class="ti ti-external-link me-1"></i> Abrir contrato en PDF
                            </a>
                        </div>
                    </x-card>

                    @if ($contract->loan)
                        <x-card title="Accesos rápidos" icon="link" color="secondary">
                            <div class="d-grid gap-2">
                                <a href="{{ route('loans.show', $contract->loan) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="ti ti-file-invoice me-1"></i> Ver préstamo
                                </a>

                                @if ($contract->client)
                                    <a href="{{ route('clients.show', $contract->client) }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="ti ti-id-badge me-1"></i> Ver ficha del cliente
                                    </a>
                                @endif
                            </div>
                        </x-card>
                    @endif
                </div>
            </div>

        </div>
    </div>
@endsection
