@extends('layouts.app')

@php
    use App\Services\Money;

    $verification = match ($payment->verification_status) {
        'pendiente' => ['label' => 'Pendiente de verificación', 'color' => 'warning', 'icon' => 'hourglass'],
        'rechazado' => ['label' => 'Comprobante rechazado', 'color' => 'danger', 'icon' => 'circle-x'],
        default => ['label' => 'Verificado', 'color' => 'success', 'icon' => 'circle-check'],
    };

    $capitalApplied = (float) $payment->amount - (float) $payment->late_fee_amount;
@endphp

@section('content')
    <x-header icon="cash-coin" title="Pago {{ $payment->receipt_number }}" text="{{ $payment->client?->name }} · {{ $payment->payment_date?->format('d/m/Y') }}">
        <x-slot:actions>
            <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver
            </a>

            <a href="{{ route('print.payments.receipt', $payment) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-printer me-1"></i> Imprimir recibo (PDF)
            </a>

            @if ($payment->comprobante_path)
                <a href="{{ route('payments.comprobante', $payment) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-paperclip me-1"></i> Ver comprobante
                </a>
            @endif
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="cash-coin" color="success" description="Monto cobrado" value="{{ Money::format((float) $payment->amount) }}" hint="Total del recibo" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="building-bank" color="primary" description="Aplicado a capital" value="{{ Money::format($capitalApplied) }}" hint="Amortización" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="alert-triangle" color="danger" description="Mora cobrada" value="{{ Money::format((float) $payment->late_fee_amount) }}" hint="Interés punitorio" />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="{{ $verification['icon'] }}"
                        color="{{ $verification['color'] }}"
                        description="Verificación"
                        value="{{ $verification['label'] }}"
                        hint="{{ $payment->verifier?->name ?? 'Sin verificar' }}"
                    />
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-xl-8">
                    <x-card title="Datos del pago" icon="receipt" color="primary">
                        <dl class="row small mb-0">
                            <dt class="col-5 col-md-4 text-muted fw-normal">Recibo</dt>
                            <dd class="col-7 col-md-8 fw-semibold">{{ $payment->receipt_number }}</dd>

                            <dt class="col-5 col-md-4 text-muted fw-normal">Cliente</dt>
                            <dd class="col-7 col-md-8">
                                @if ($payment->client)
                                    <a href="{{ route('clients.show', $payment->client) }}" class="text-decoration-none">{{ $payment->client->name }}</a>
                                    <div class="small text-muted">{{ $payment->client->phone }}</div>
                                @else
                                    —
                                @endif
                            </dd>

                            <dt class="col-5 col-md-4 text-muted fw-normal">Préstamo</dt>
                            <dd class="col-7 col-md-8">
                                @if ($payment->loan)
                                    <a href="{{ route('loans.show', $payment->loan) }}" class="text-decoration-none">#{{ $payment->loan_id }}</a>
                                @else
                                    #{{ $payment->loan_id }}
                                @endif
                            </dd>

                            <dt class="col-5 col-md-4 text-muted fw-normal">Cuota</dt>
                            <dd class="col-7 col-md-8">
                                {{ $payment->isPrepayment() ? 'Anticipo a capital' : '#'.($payment->installment?->installment_number ?? '—') }}
                            </dd>

                            <dt class="col-5 col-md-4 text-muted fw-normal">Fecha de pago</dt>
                            <dd class="col-7 col-md-8">{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</dd>

                            <dt class="col-5 col-md-4 text-muted fw-normal">Método</dt>
                            <dd class="col-7 col-md-8">{{ $payment->method?->label() ?? '—' }}</dd>

                            <dt class="col-5 col-md-4 text-muted fw-normal">Estado del pago</dt>
                            <dd class="col-7 col-md-8"><x-status-badge :status="$payment->status" /></dd>

                            <dt class="col-5 col-md-4 text-muted fw-normal">Verificación</dt>
                            <dd class="col-7 col-md-8">
                                <span class="badge rounded-pill text-bg-{{ $verification['color'] }}">{{ $verification['label'] }}</span>
                                @if ($payment->verified_at)
                                    <div class="small text-muted mt-1">
                                        {{ $payment->verified_at->format('d/m/Y H:i') }}
                                        @if ($payment->verifier)
                                            · {{ $payment->verifier->name }}
                                        @endif
                                    </div>
                                @endif
                            </dd>

                            <dt class="col-5 col-md-4 text-muted fw-normal">Registrado</dt>
                            <dd class="col-7 col-md-8">{{ $payment->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>

                            @if ($payment->notes)
                                <dt class="col-5 col-md-4 text-muted fw-normal">Notas</dt>
                                <dd class="col-7 col-md-8" style="white-space: pre-line;">{{ $payment->notes }}</dd>
                            @endif

                            @if ($payment->cancellation_reason)
                                <dt class="col-5 col-md-4 text-muted fw-normal">Motivo de anulación</dt>
                                <dd class="col-7 col-md-8 text-danger">{{ $payment->cancellation_reason }}</dd>
                            @endif
                        </dl>
                    </x-card>

                    @if ($payment->isPendingVerification() && auth()->user()?->can('payments.manage'))
                        <x-card title="Verificar comprobante" icon="shield-check" color="warning">
                            <p class="small text-muted">
                                Confirmá si el comprobante adjunto corresponde al pago. La decisión queda registrada con tu usuario.
                            </p>

                            <form method="POST" action="{{ route('payments.verify', $payment) }}" class="row g-3">
                                @csrf

                                <div class="col-12 col-md-4">
                                    <label for="verification_status" class="form-label">Resultado <span class="text-danger">*</span></label>
                                    <select class="form-select @error('verification_status') is-invalid @enderror" id="verification_status" name="verification_status" required>
                                        <option value="verificado" @selected(old('verification_status') === 'verificado')>Verificado</option>
                                        <option value="rechazado" @selected(old('verification_status') === 'rechazado')>Rechazado</option>
                                    </select>
                                    @error('verification_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-8">
                                    <label for="verification_notes" class="form-label">Notas de verificación</label>
                                    <input type="text" class="form-control @error('verification_notes') is-invalid @enderror" id="verification_notes" name="verification_notes" value="{{ old('verification_notes') }}" maxlength="500">
                                    @error('verification_notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="ti ti-shield-check me-1"></i> Guardar verificación
                                    </button>
                                </div>
                            </form>
                        </x-card>
                    @endif

                    @if ($payment->status === \App\Enums\PaymentStatus::Active && auth()->user()?->can('payments.manage'))
                        <x-card title="Anular pago" icon="circle-x" color="danger">
                            <p class="small text-muted">
                                La anulación reabre la cuota y deja el pago fuera de los totales. No se puede deshacer.
                            </p>

                            <form
                                method="POST"
                                action="{{ route('payments.cancel', $payment) }}"
                                data-confirm-delete
                                data-confirm-title="Anular pago"
                                data-confirm-text="{{ 'Se anulará el recibo '.$payment->receipt_number.' por '.Money::format((float) $payment->amount).' y se reabrirá la cuota.' }}"
                                data-confirm-button="Sí, anular"
                                data-confirm-cancel="Cancelar"
                            >
                                @csrf

                                <div class="row g-3">
                                    <div class="col-12 col-md-8">
                                        <label for="cancellation_reason" class="form-label">Motivo <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('cancellation_reason') is-invalid @enderror" id="cancellation_reason" name="cancellation_reason" minlength="5" maxlength="500" required>
                                        @error('cancellation_reason')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12 col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-outline-danger w-100">
                                            <i class="ti ti-circle-x me-1"></i> Anular pago
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </x-card>
                    @endif
                </div>

                <div class="col-12 col-xl-4">
                    <x-card title="Desglose" icon="calculator" color="info">
                        <dl class="row small mb-0">
                            <dt class="col-7 text-muted fw-normal">Capital / interés</dt>
                            <dd class="col-5 text-end">{{ Money::format($capitalApplied) }}</dd>

                            <dt class="col-7 text-muted fw-normal">Mora</dt>
                            <dd class="col-5 text-end">{{ Money::format((float) $payment->late_fee_amount) }}</dd>

                            <dt class="col-7 fw-semibold border-top pt-2 mt-2">Total</dt>
                            <dd class="col-5 text-end fw-bold border-top pt-2 mt-2">{{ Money::format((float) $payment->amount) }}</dd>
                        </dl>
                    </x-card>

                    <x-card title="Accesos rápidos" icon="link" color="secondary">
                        <div class="d-grid gap-2">
                            @if ($payment->client)
                                <a href="{{ route('clients.show', $payment->client) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="ti ti-id-badge me-1"></i> Ver ficha del cliente
                                </a>
                            @endif

                            @if ($payment->loan)
                                <a href="{{ route('loans.show', $payment->loan) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="ti ti-file-invoice me-1"></i> Ver préstamo
                                </a>
                            @endif

                            <a href="{{ route('payments.create') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-plus me-1"></i> Registrar otro pago
                            </a>
                        </div>
                    </x-card>
                </div>
            </div>

        </div>
    </div>
@endsection
