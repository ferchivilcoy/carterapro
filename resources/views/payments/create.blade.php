@extends('layouts.app')

@php
    use App\Services\Money;

    $selectedInstallment = $installmentId ? $installments->firstWhere('id', $installmentId) : null;
    $initialAmount = $selectedInstallment
        ? (float) $selectedInstallment->pending_balance + (float) $selectedInstallment->late_fee
        : null;
@endphp

@section('content')
    <x-header icon="cash-coin" title="Registrar pago" text="Cargá el cobro sobre una cuota del plan y adjuntá el comprobante si corresponde.">
        <x-slot:actions>
            <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver al listado
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            @if ($installments->isEmpty())
                <x-card title="Sin cuotas por cobrar" icon="calendar-check" color="warning">
                    <x-empty-state
                        icon="calendar-check"
                        title="No hay cuotas pendientes"
                        text="Todas las cuotas de la cartera están pagadas o anuladas."
                    />

                    <div class="text-center">
                        <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary">
                            <i class="ti ti-file-invoice me-1"></i> Ir a préstamos
                        </a>
                    </div>
                </x-card>
            @else
                <form method="POST" action="{{ route('payments.store') }}" enctype="multipart/form-data" id="payment-form">
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
                            <x-card title="Datos del pago" subtitle="Los campos marcados con * son obligatorios." icon="cash-coin" color="success">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="installment_id" class="form-label">Cuota a cobrar <span class="text-danger">*</span></label>
                                        <select class="form-select @error('installment_id') is-invalid @enderror" id="installment_id" name="installment_id" required>
                                            <option value="">Seleccioná la cuota</option>

                                            @foreach ($installments->groupBy(fn ($installment) => $installment->loan?->client?->name ?? 'Cliente sin asignar') as $clientName => $clientInstallments)
                                                <optgroup label="{{ $clientName }}">
                                                    @foreach ($clientInstallments as $installment)
                                                        <option
                                                            value="{{ $installment->id }}"
                                                            data-pending="{{ (float) $installment->pending_balance }}"
                                                            data-late-fee="{{ (float) $installment->late_fee }}"
                                                            data-expected="{{ (float) $installment->expected_amount }}"
                                                            data-paid="{{ (float) $installment->paid_amount }}"
                                                            data-due="{{ $installment->due_date?->format('d/m/Y') }}"
                                                            data-status="{{ $installment->status->label() }}"
                                                            data-loan="{{ $installment->loan_id }}"
                                                            data-number="{{ $installment->installment_number }}"
                                                            @selected((string) old('installment_id', $installmentId) === (string) $installment->id)
                                                        >
                                                            Préstamo #{{ $installment->loan_id }} · Cuota #{{ $installment->installment_number }} · vence {{ $installment->due_date?->format('d/m/Y') }} · pendiente {{ Money::format((float) $installment->pending_balance) }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                        @error('installment_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12 col-md-4">
                                        <label for="amount" class="form-label">Monto total <span class="text-danger">*</span></label>
                                        <div class="input-group has-validation">
                                            <span class="input-group-text">$</span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                class="form-control @error('amount') is-invalid @enderror"
                                                id="amount"
                                                name="amount"
                                                value="{{ old('amount', $initialAmount) }}"
                                                required
                                            >
                                            @error('amount')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="form-text">Incluye capital, interés y mora.</div>
                                    </div>

                                    <div class="col-12 col-md-4">
                                        <label for="payment_date" class="form-label">Fecha de pago <span class="text-danger">*</span></label>
                                        <input
                                            type="date"
                                            class="form-control @error('payment_date') is-invalid @enderror"
                                            id="payment_date"
                                            name="payment_date"
                                            value="{{ old('payment_date', today()->toDateString()) }}"
                                            max="{{ today()->toDateString() }}"
                                            required
                                        >
                                        @error('payment_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12 col-md-4">
                                        <label for="method" class="form-label">Método <span class="text-danger">*</span></label>
                                        <select class="form-select @error('method') is-invalid @enderror" id="method" name="method" required>
                                            @foreach ($methodOptions as $option)
                                                <option value="{{ $option->value }}" @selected(old('method', 'cash') === $option->value)>{{ $option->label() }}</option>
                                            @endforeach
                                        </select>
                                        @error('method')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label for="notes" class="form-label">Notas</label>
                                        <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" maxlength="500">{{ old('notes') }}</textarea>
                                        @error('notes')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label for="comprobante" class="form-label">Comprobante</label>
                                        <input
                                            type="file"
                                            class="form-control @error('comprobante') is-invalid @enderror"
                                            id="comprobante"
                                            name="comprobante"
                                            accept=".jpg,.jpeg,.png,.pdf"
                                        >
                                        <div class="form-text">
                                            JPG, PNG o PDF de hasta 10 MB. Si adjuntás un comprobante, el pago queda pendiente de verificación.
                                        </div>
                                        @error('comprobante')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end flex-wrap gap-2 border-top pt-3 mt-4">
                                    <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="ti ti-circle-check me-1"></i> Registrar pago
                                    </button>
                                </div>
                            </x-card>
                        </div>

                        <div class="col-12 col-xl-4">
                            <x-card title="Detalle de la cuota" icon="clipboard-data" color="info">
                                <div id="installment-empty" class="{{ $selectedInstallment ? 'd-none' : '' }}">
                                    <x-empty-state
                                        icon="hand-click"
                                        title="Elegí una cuota"
                                        text="Al seleccionar la cuota vas a ver su saldo y su mora."
                                    />
                                </div>

                                <dl class="row small mb-0 {{ $selectedInstallment ? '' : 'd-none' }}" id="installment-detail">
                                    <dt class="col-6 text-muted fw-normal">Préstamo</dt>
                                    <dd class="col-6 text-end fw-semibold" id="detail-loan">—</dd>

                                    <dt class="col-6 text-muted fw-normal">Cuota</dt>
                                    <dd class="col-6 text-end" id="detail-number">—</dd>

                                    <dt class="col-6 text-muted fw-normal">Vencimiento</dt>
                                    <dd class="col-6 text-end" id="detail-due">—</dd>

                                    <dt class="col-6 text-muted fw-normal">Estado</dt>
                                    <dd class="col-6 text-end" id="detail-status">—</dd>

                                    <dt class="col-6 text-muted fw-normal">Esperado</dt>
                                    <dd class="col-6 text-end" id="detail-expected">—</dd>

                                    <dt class="col-6 text-muted fw-normal">Ya pagado</dt>
                                    <dd class="col-6 text-end" id="detail-paid">—</dd>

                                    <dt class="col-6 text-muted fw-normal">Saldo</dt>
                                    <dd class="col-6 text-end fw-semibold" id="detail-pending">—</dd>

                                    <dt class="col-6 text-muted fw-normal">Mora</dt>
                                    <dd class="col-6 text-end text-danger" id="detail-late-fee">—</dd>

                                    <dt class="col-6 fw-semibold border-top pt-2 mt-2">Total a cobrar</dt>
                                    <dd class="col-6 text-end fw-bold border-top pt-2 mt-2" id="detail-total">—</dd>
                                </dl>
                            </x-card>

                            <x-card title="Antes de guardar" icon="bulb" color="warning">
                                <ul class="small text-muted mb-0 ps-3">
                                    <li class="mb-2">El monto se precarga con el saldo más la mora; podés ajustarlo si el cliente entrega otro importe.</li>
                                    <li class="mb-2">La fecha de pago no puede ser futura.</li>
                                    <li>Con comprobante adjunto, el cobro queda <strong>pendiente de verificación</strong> hasta confirmarlo en el detalle del pago.</li>
                                </ul>
                            </x-card>
                        </div>
                    </div>
                </form>
            @endif

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const select = document.getElementById('installment_id');
            const amount = document.getElementById('amount');

            if (!select || !amount) {
                return;
            }

            const empty = document.getElementById('installment-empty');
            const detail = document.getElementById('installment-detail');

            const fields = {
                loan: document.getElementById('detail-loan'),
                number: document.getElementById('detail-number'),
                due: document.getElementById('detail-due'),
                status: document.getElementById('detail-status'),
                expected: document.getElementById('detail-expected'),
                paid: document.getElementById('detail-paid'),
                pending: document.getElementById('detail-pending'),
                lateFee: document.getElementById('detail-late-fee'),
                total: document.getElementById('detail-total'),
            };

            const money = new Intl.NumberFormat('es-AR', {
                style: 'currency',
                currency: @json(\App\Models\Setting::get('currency', \App\Models\Setting::DEFAULTS['currency'])),
                minimumFractionDigits: 2,
            });

            function sync() {
                const option = select.options[select.selectedIndex];
                const hasOption = option && option.value !== '';

                empty.classList.toggle('d-none', hasOption);
                detail.classList.toggle('d-none', !hasOption);

                if (!hasOption) {
                    return;
                }

                const pending = parseFloat(option.dataset.pending || 0);
                const lateFee = parseFloat(option.dataset.lateFee || 0);

                fields.loan.textContent = '#' + option.dataset.loan;
                fields.number.textContent = '#' + option.dataset.number;
                fields.due.textContent = option.dataset.due;
                fields.status.textContent = option.dataset.status;
                fields.expected.textContent = money.format(parseFloat(option.dataset.expected || 0));
                fields.paid.textContent = money.format(parseFloat(option.dataset.paid || 0));
                fields.pending.textContent = money.format(pending);
                fields.lateFee.textContent = money.format(lateFee);
                fields.total.textContent = money.format(pending + lateFee);
            }

            select.addEventListener('change', function () {
                const option = select.options[select.selectedIndex];

                if (option && option.value !== '') {
                    amount.value = (parseFloat(option.dataset.pending || 0) + parseFloat(option.dataset.lateFee || 0)).toFixed(2);
                }

                sync();
            });

            sync();
        })();
    </script>
@endpush
