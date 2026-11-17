@extends('layouts.app')

@php
    use App\Services\Money;

    $planLocked = $hasPayments;
@endphp

@section('content')
    <x-header icon="edit" title="Editar préstamo #{{ $loan->id }}" text="{{ $loan->client?->name }} · {{ $loan->status->label() }}">
        <x-slot:actions>
            <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver al listado
            </a>

            <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-eye me-1"></i> Ver detalle
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <form method="POST" action="{{ route('loans.update', $loan) }}">
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <div class="alert alert-danger d-flex gap-2" role="alert">
                        <i class="ti ti-alert-triangle flex-shrink-0"></i>
                        <div>
                            <p class="fw-semibold mb-1">Revisá los datos ingresados:</p>
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <div class="row">
                    <div class="col-12 col-xl-8">
                        <x-card title="Datos del préstamo" subtitle="El cliente del préstamo no se puede cambiar." icon="file-invoice" color="primary">
                            <div class="row g-3">
                                <div class="col-12 col-md-8">
                                    <label class="form-label">Cliente</label>
                                    <input type="text" class="form-control" value="{{ $loan->client?->name ?? 'Cliente sin asignar' }}" disabled>
                                    <div class="form-text">
                                        {{ $loan->client?->document ? 'DNI '.$loan->client->document.' · ' : '' }}{{ $loan->client?->phone }}
                                    </div>
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label">Estado</label>
                                    <div class="form-control d-flex align-items-center" style="min-height: 2.375rem;">
                                        <x-status-badge :status="$loan->status" />
                                    </div>
                                </div>
                            </div>

                            @if ($planLocked)
                                <div class="alert alert-warning d-flex gap-2 mt-3 mb-0" role="note">
                                    <i class="ti ti-lock flex-shrink-0"></i>
                                    <div class="small">
                                        El préstamo ya tiene pagos registrados, así que las condiciones del plan
                                        (monto, tasa, modelo, cuotas, frecuencia e inicio) no se pueden modificar.
                                        Sólo podés actualizar la forma de entrega y las notas.
                                    </div>
                                </div>
                            @endif

                            <hr class="my-4">

                            <h3 class="h6 text-uppercase text-muted fw-semibold mb-3">Condiciones del plan</h3>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="principal_amount" class="form-label">Monto del préstamo <span class="text-danger">*</span></label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text">$</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="{{ $limits['min'] }}"
                                            max="{{ $limits['max'] }}"
                                            class="form-control @error('principal_amount') is-invalid @enderror"
                                            id="principal_amount"
                                            name="principal_amount"
                                            value="{{ old('principal_amount', (float) $loan->principal_amount) }}"
                                            @disabled($planLocked)
                                            required
                                        >
                                        @error('principal_amount')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-text">Entre {{ Money::format($limits['min']) }} y {{ Money::format($limits['max']) }}.</div>
                                </div>

                                <div class="col-12 col-md-3">
                                    <label for="interest_rate" class="form-label">Tasa de interés (%) <span class="text-danger">*</span></label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="form-control @error('interest_rate') is-invalid @enderror"
                                        id="interest_rate"
                                        name="interest_rate"
                                        value="{{ old('interest_rate', (float) $loan->interest_rate) }}"
                                        @disabled($planLocked)
                                        required
                                    >
                                    @error('interest_rate')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-3">
                                    <label for="rate_type" class="form-label">Tipo de tasa <span class="text-danger">*</span></label>
                                    <select class="form-select @error('rate_type') is-invalid @enderror" id="rate_type" name="rate_type" @disabled($planLocked) required>
                                        @foreach ($rateTypes as $rateType)
                                            <option value="{{ $rateType->value }}" @selected(old('rate_type', $loan->rate_type?->value) === $rateType->value)>{{ $rateType->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('rate_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="amortization_model" class="form-label">Modelo de amortización <span class="text-danger">*</span></label>
                                    <select class="form-select @error('amortization_model') is-invalid @enderror" id="amortization_model" name="amortization_model" @disabled($planLocked) required>
                                        @foreach ($amortizationModels as $model)
                                            <option value="{{ $model->value }}" @selected(old('amortization_model', $loan->amortization_model?->value) === $model->value)>{{ $model->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('amortization_model')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-3">
                                    <label for="installments_count" class="form-label">Cantidad de cuotas <span class="text-danger">*</span></label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="120"
                                        class="form-control @error('installments_count') is-invalid @enderror"
                                        id="installments_count"
                                        name="installments_count"
                                        value="{{ old('installments_count', $loan->installments_count) }}"
                                        @disabled($planLocked)
                                        required
                                    >
                                    @error('installments_count')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-3">
                                    <label for="frequency" class="form-label">Frecuencia <span class="text-danger">*</span></label>
                                    <select class="form-select @error('frequency') is-invalid @enderror" id="frequency" name="frequency" @disabled($planLocked) required>
                                        @foreach ($frequencies as $frequency)
                                            <option value="{{ $frequency->value }}" @selected(old('frequency', $loan->frequency?->value) === $frequency->value)>{{ $frequency->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('frequency')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-4">
                                    <label for="start_date" class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
                                    <input
                                        type="date"
                                        class="form-control @error('start_date') is-invalid @enderror"
                                        id="start_date"
                                        name="start_date"
                                        value="{{ old('start_date', $loan->start_date?->toDateString()) }}"
                                        @disabled($planLocked)
                                        required
                                    >
                                    @error('start_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <hr class="my-4">

                            <h3 class="h6 text-uppercase text-muted fw-semibold mb-3">Entrega del dinero</h3>

                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label for="disbursement_method" class="form-label">Forma de entrega <span class="text-danger">*</span></label>
                                    <select class="form-select @error('disbursement_method') is-invalid @enderror" id="disbursement_method" name="disbursement_method" required>
                                        @foreach ($disbursementMethods as $method)
                                            <option value="{{ $method->value }}" @selected(old('disbursement_method', $loan->disbursement_method?->value ?? 'cash') === $method->value)>{{ $method->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('disbursement_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-8" id="disbursement-account-wrapper">
                                    <label for="disbursement_account" class="form-label">Cuenta de destino</label>
                                    <select class="form-select @error('disbursement_account') is-invalid @enderror" id="disbursement_account" name="disbursement_account">
                                        <option value="">Seleccioná una cuenta</option>
                                        @if ($clientAccount)
                                            <option value="{{ $clientAccount['value'] }}" @selected(old('disbursement_account', $loan->disbursement_account) === $clientAccount['value'])>
                                                {{ $clientAccount['label'] }}
                                            </option>
                                        @endif
                                    </select>
                                    <div class="form-text">Sólo se ofrecen las cuentas cargadas en la ficha del cliente.</div>
                                    @error('disbursement_account')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label for="notes" class="form-label">Notas</label>
                                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes', $loan->notes) }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-end flex-wrap gap-2 border-top pt-3 mt-4">
                                <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-circle-check me-1"></i> Guardar cambios
                                </button>
                            </div>
                        </x-card>
                    </div>

                    <div class="col-12 col-xl-4">
                        <x-card title="Resumen del préstamo" icon="clipboard-data" color="info">
                            <dl class="row small mb-0">
                                <dt class="col-6 text-muted fw-normal">Capital</dt>
                                <dd class="col-6 text-end fw-semibold">{{ Money::format((float) $loan->principal_amount) }}</dd>

                                <dt class="col-6 text-muted fw-normal">Total a devolver</dt>
                                <dd class="col-6 text-end">{{ Money::format((float) $loan->total_amount) }}</dd>

                                <dt class="col-6 text-muted fw-normal">Saldo pendiente</dt>
                                <dd class="col-6 text-end fw-semibold">{{ Money::format((float) $loan->pending_balance) }}</dd>

                                <dt class="col-6 text-muted fw-normal">Cuotas</dt>
                                <dd class="col-6 text-end">{{ $loan->installments_count }}</dd>

                                <dt class="col-6 text-muted fw-normal">Pagos registrados</dt>
                                <dd class="col-6 text-end">{{ $loan->payments()->count() }}</dd>
                            </dl>
                        </x-card>

                        <x-card title="Qué ocurre al guardar" icon="bulb" color="warning">
                            <ul class="small text-muted mb-0 ps-3">
                                @if ($planLocked)
                                    <li class="mb-2">Se actualizan la forma de entrega y las notas.</li>
                                    <li>El plan de cuotas y el saldo no se tocan.</li>
                                @else
                                    <li class="mb-2">Si cambiás una condición del plan, el sistema <strong>regenera las cuotas</strong>.</li>
                                    <li class="mb-2">El total y el saldo pendiente se recalculan.</li>
                                    <li>La edición queda registrada en la bitácora del préstamo.</li>
                                @endif
                            </ul>
                        </x-card>
                    </div>
                </div>
            </form>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const methodSelect = document.getElementById('disbursement_method');
            const accountWrapper = document.getElementById('disbursement-account-wrapper');
            const accountSelect = document.getElementById('disbursement_account');

            function syncMethod() {
                const isTransfer = methodSelect.value === 'transfer';
                accountWrapper.classList.toggle('d-none', !isTransfer);
                accountSelect.required = isTransfer;
            }

            methodSelect.addEventListener('change', syncMethod);
            syncMethod();
        })();
    </script>
@endpush
