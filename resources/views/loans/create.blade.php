@extends('layouts.app')

@php
    use App\Services\Money;
@endphp

@section('content')
    <x-header icon="file-plus" title="Nuevo préstamo" text="Definí las condiciones del plan y revisá la vista previa antes de guardar.">
        <x-slot:actions>
            <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver al listado
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <form method="POST" action="{{ route('loans.store') }}" id="loan-form">
                @csrf

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
                        <x-card title="Condiciones del préstamo" subtitle="Los campos marcados con * son obligatorios." icon="file-invoice" color="primary">
                            <div class="row g-3">
                                <div class="col-12 col-md-8">
                                    <label for="client_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                    <select class="form-select @error('client_id') is-invalid @enderror" id="client_id" name="client_id" required>
                                        <option value="">Seleccioná un cliente</option>
                                        @foreach ($clients as $client)
                                            <option value="{{ $client->id }}" @selected((string) old('client_id', $clientId) === (string) $client->id)>
                                                {{ $client->name }}{{ $client->document ? ' · DNI '.$client->document : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('client_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-4">
                                    <label for="start_date" class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('start_date') is-invalid @enderror" id="start_date" name="start_date" value="{{ old('start_date', $startDate) }}" required>
                                    @error('start_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="principal_amount" class="form-label">Monto del préstamo <span class="text-danger">*</span></label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="{{ $limits['min'] }}" max="{{ $limits['max'] }}" class="form-control @error('principal_amount') is-invalid @enderror" id="principal_amount" name="principal_amount" value="{{ old('principal_amount', $defaults['min_amount']) }}" required>
                                        @error('principal_amount')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-text">Entre {{ Money::format($limits['min']) }} y {{ Money::format($limits['max']) }}.</div>
                                </div>

                                <div class="col-12 col-md-3">
                                    <label for="interest_rate" class="form-label">Tasa de interés (%) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control @error('interest_rate') is-invalid @enderror" id="interest_rate" name="interest_rate" value="{{ old('interest_rate', $defaults['interest_rate']) }}" required>
                                    @error('interest_rate')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-3">
                                    <label for="rate_type" class="form-label">Tipo de tasa <span class="text-danger">*</span></label>
                                    <select class="form-select @error('rate_type') is-invalid @enderror" id="rate_type" name="rate_type" required>
                                        @foreach ($rateTypes as $rateType)
                                            <option value="{{ $rateType->value }}" @selected(old('rate_type', $defaults['rate_type']) === $rateType->value)>{{ $rateType->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('rate_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="amortization_model" class="form-label">Modelo de amortización <span class="text-danger">*</span></label>
                                    <select class="form-select @error('amortization_model') is-invalid @enderror" id="amortization_model" name="amortization_model" required>
                                        @foreach ($amortizationModels as $model)
                                            <option value="{{ $model->value }}" @selected(old('amortization_model', $defaults['amortization_model']) === $model->value)>{{ $model->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('amortization_model')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-3">
                                    <label for="installments_count" class="form-label">Cantidad de cuotas <span class="text-danger">*</span></label>
                                    <input type="number" min="1" max="120" class="form-control @error('installments_count') is-invalid @enderror" id="installments_count" name="installments_count" value="{{ old('installments_count', $defaults['installments_count']) }}" required>
                                    @error('installments_count')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-3">
                                    <label for="frequency" class="form-label">Frecuencia <span class="text-danger">*</span></label>
                                    <select class="form-select @error('frequency') is-invalid @enderror" id="frequency" name="frequency" required>
                                        @foreach ($frequencies as $frequency)
                                            <option value="{{ $frequency->value }}" @selected(old('frequency', $defaults['frequency']) === $frequency->value)>{{ $frequency->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('frequency')
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
                                            <option value="{{ $method->value }}" @selected(old('disbursement_method', 'cash') === $method->value)>{{ $method->label() }}</option>
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
                                    </select>
                                    <div class="form-text">Sólo se ofrecen las cuentas cargadas en la ficha del cliente.</div>
                                    @error('disbursement_account')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label for="notes" class="form-label">Notas</label>
                                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" placeholder="Condiciones especiales, observaciones de la entrega, etc.">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-end flex-wrap gap-2 border-top pt-3 mt-4">
                                <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                                <button type="button" class="btn btn-outline-primary" id="preview-button">
                                    <i class="ti ti-calculator me-1"></i> Actualizar vista previa
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-circle-check me-1"></i> Guardar préstamo
                                </button>
                            </div>
                        </x-card>
                    </div>

                    <div class="col-12 col-xl-4">
                        <x-card title="Resumen del plan" icon="clipboard-data" color="info">
                            <dl class="row small mb-0">
                                <dt class="col-6 text-muted fw-normal">Capital</dt>
                                <dd class="col-6 text-end fw-semibold" id="summary-principal">{{ Money::format($defaults['min_amount']) }}</dd>

                                <dt class="col-6 text-muted fw-normal">Interés estimado</dt>
                                <dd class="col-6 text-end" id="summary-interest">—</dd>

                                <dt class="col-6 text-muted fw-normal">Total a devolver</dt>
                                <dd class="col-6 text-end fw-semibold" id="summary-total">—</dd>

                                <dt class="col-6 text-muted fw-normal">Cuota estimada</dt>
                                <dd class="col-6 text-end" id="summary-installment">—</dd>
                            </dl>

                            <div class="alert alert-light border small text-muted mt-3 mb-0" role="note">
                                <i class="ti ti-info-circle text-primary me-1"></i>
                                La vista previa se calcula con el mismo motor que usa el alta. Si cambiás una condición, actualizala antes de guardar.
                            </div>
                        </x-card>

                        <x-card title="Cómo se calcula" icon="bulb" color="warning">
                            <ul class="small text-muted mb-0 ps-3">
                                <li class="mb-2"><strong>Interés fijo:</strong> se calcula sobre el monto original.</li>
                                <li class="mb-2"><strong>Interés sobre saldo:</strong> se calcula sobre el capital pendiente.</li>
                                <li class="mb-2"><strong>Cuota fija (francés):</strong> cuotas iguales con tasa por período.</li>
                                <li>Los modelos de saldo y francés requieren tasa <strong>Mensual</strong>.</li>
                            </ul>
                        </x-card>
                    </div>
                </div>

                <x-card title="Vista previa del plan de cuotas" subtitle="Se muestra el plan calculado sin guardar nada todavía." icon="table" color="secondary">
                    <div id="preview-empty" class="text-center text-muted small py-4">
                        <i class="ti ti-calculator fs-4 d-block mb-2"></i>
                        Completá las condiciones y presioná “Actualizar vista previa”.
                    </div>

                    <div id="preview-result" class="d-none">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Cuota</th>
                                        <th>Vencimiento</th>
                                        <th class="text-end">Monto</th>
                                    </tr>
                                </thead>
                                <tbody id="preview-body"></tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between gap-2 border-top pt-3 mt-3 small">
                            <span class="text-muted">Primera cuota: <span class="fw-semibold" id="preview-first">—</span></span>
                            <span class="text-muted">Última cuota: <span class="fw-semibold" id="preview-last">—</span></span>
                            <span class="text-muted">Cuota máxima: <span class="fw-semibold" id="preview-max">—</span></span>
                        </div>
                    </div>

                    <div id="preview-error" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
                </x-card>
            </form>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const form = document.getElementById('loan-form');
            const clientAccounts = @json($clientAccounts);
            const clientSelect = document.getElementById('client_id');
            const accountSelect = document.getElementById('disbursement_account');
            const accountWrapper = document.getElementById('disbursement-account-wrapper');
            const methodSelect = document.getElementById('disbursement_method');
            const previewButton = document.getElementById('preview-button');
            const previewBody = document.getElementById('preview-body');
            const previewEmpty = document.getElementById('preview-empty');
            const previewResult = document.getElementById('preview-result');
            const previewError = document.getElementById('preview-error');
            const oldAccount = @json(old('disbursement_account'));

            const currency = @json($defaults['currency']);
            const money = new Intl.NumberFormat('es-AR', {
                style: 'currency',
                currency: currency,
                minimumFractionDigits: 2,
            });

            function syncAccounts() {
                const selected = String(clientSelect.value || '');
                const accounts = clientAccounts[selected] || null;

                accountSelect.innerHTML = '<option value="">Seleccioná una cuenta</option>';

                if (accounts) {
                    const option = document.createElement('option');
                    option.value = accounts.value;
                    option.textContent = accounts.label;
                    option.selected = oldAccount === accounts.value;
                    accountSelect.appendChild(option);
                }

                syncMethod();
            }

            function syncMethod() {
                const isTransfer = methodSelect.value === 'transfer';
                accountWrapper.classList.toggle('d-none', !isTransfer);
                accountSelect.required = isTransfer;
            }

            function collectData() {
                return {
                    principal_amount: document.getElementById('principal_amount').value,
                    interest_rate: document.getElementById('interest_rate').value,
                    rate_type: document.getElementById('rate_type').value,
                    amortization_model: document.getElementById('amortization_model').value,
                    installments_count: document.getElementById('installments_count').value,
                    frequency: document.getElementById('frequency').value,
                    start_date: document.getElementById('start_date').value,
                };
            }

            async function refreshPreview() {
                previewError.classList.add('d-none');
                previewButton.disabled = true;
                previewButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Calculando…';

                try {
                    const response = await fetch(@json(route('loans.preview')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                        },
                        body: JSON.stringify(collectData()),
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        const messages = payload.errors ? Object.values(payload.errors).flat() : [payload.message || 'No se pudo calcular el plan.'];
                        throw new Error(messages.join(' '));
                    }

                    previewBody.innerHTML = payload.installments.map((installment) => `
                        <tr>
                            <td class="fw-semibold">#${installment.n}</td>
                            <td>${installment.due_date}</td>
                            <td class="text-end">${money.format(installment.amount)}</td>
                        </tr>
                    `).join('');

                    document.getElementById('preview-first').textContent = money.format(payload.first_amount);
                    document.getElementById('preview-last').textContent = money.format(payload.last_amount);
                    document.getElementById('preview-max').textContent = money.format(payload.max_amount);

                    document.getElementById('summary-principal').textContent = money.format(parseFloat(collectData().principal_amount || 0));
                    document.getElementById('summary-interest').textContent = money.format(payload.interest_amount);
                    document.getElementById('summary-total').textContent = money.format(payload.total_amount);
                    document.getElementById('summary-installment').textContent = money.format(payload.first_amount);

                    previewEmpty.classList.add('d-none');
                    previewResult.classList.remove('d-none');
                } catch (error) {
                    previewError.textContent = error.message;
                    previewError.classList.remove('d-none');
                } finally {
                    previewButton.disabled = false;
                    previewButton.innerHTML = '<i class="ti ti-calculator me-1"></i> Actualizar vista previa';
                }
            }

            clientSelect.addEventListener('change', syncAccounts);
            methodSelect.addEventListener('change', syncMethod);
            previewButton.addEventListener('click', refreshPreview);

            syncAccounts();

            if (clientSelect.value) {
                refreshPreview();
            }
        })();
    </script>
@endpush
