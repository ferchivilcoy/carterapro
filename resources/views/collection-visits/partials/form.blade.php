@php
    $pendingPayments = $pendingPayments ?? collect();
    $selectedClient = old('client_id');
@endphp

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

<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="client_id" class="form-label">Cliente <span class="text-danger">*</span></label>
        <select class="form-select @error('client_id') is-invalid @enderror" id="client_id" name="client_id" required>
            <option value="">Seleccioná un cliente</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected((string) $selectedClient === (string) $client->id)>
                    {{ $client->name }}{{ $client->phone ? ' · '.$client->phone : '' }}
                </option>
            @endforeach
        </select>
        @error('client_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="type" class="form-label">Canal <span class="text-danger">*</span></label>
        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
            @foreach ($types as $case)
                <option value="{{ $case->value }}" @selected(old('type', 'whatsapp') === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
        @error('type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="result" class="form-label">Resultado <span class="text-danger">*</span></label>
        <select class="form-select @error('result') is-invalid @enderror" id="result" name="result" required>
            @foreach ($results as $case)
                <option value="{{ $case->value }}" @selected(old('result', 'contact_successful') === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
        @error('result')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="loan_id" class="form-label">Préstamo</label>
        <select class="form-select @error('loan_id') is-invalid @enderror" id="loan_id" name="loan_id">
            <option value="">Sin préstamo asociado</option>
            @foreach ($loans as $loan)
                <option value="{{ $loan->id }}" @selected((string) old('loan_id') === (string) $loan->id)>
                    #{{ $loan->id }} · {{ $loan->client?->name ?? 'Cliente sin asignar' }} · saldo {{ \App\Services\Money::format((float) $loan->pending_balance) }}
                </option>
            @endforeach
        </select>
        @error('loan_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="installment_id" class="form-label">Cuota</label>
        <select class="form-select @error('installment_id') is-invalid @enderror" id="installment_id" name="installment_id">
            <option value="">Sin cuota asociada</option>
            @foreach ($installments as $installment)
                <option value="{{ $installment->id }}" @selected((string) old('installment_id') === (string) $installment->id)>
                    Préstamo #{{ $installment->loan_id }} · cuota #{{ $installment->installment_number }} · vence {{ $installment->due_date?->format('d/m/Y') }} · {{ \App\Services\Money::format((float) $installment->pending_balance) }}
                </option>
            @endforeach
        </select>
        @error('installment_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @if ($pendingPayments->isNotEmpty())
        <div class="col-12">
            <label for="payment_id" class="form-label">Pago informado</label>
            <select class="form-select @error('payment_id') is-invalid @enderror" id="payment_id" name="payment_id">
                <option value="">Sin pago asociado</option>
                @foreach ($pendingPayments as $payment)
                    <option value="{{ $payment->id }}" @selected((string) old('payment_id') === (string) $payment->id)>
                        {{ $payment->receipt_number }} · {{ $payment->client?->name ?? 'Cliente sin asignar' }} · {{ \App\Services\Money::format((float) $payment->amount) }}
                    </option>
                @endforeach
            </select>
            <div class="form-text">Pagos con comprobante pendientes de verificación.</div>
            @error('payment_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="col-12 col-md-3">
        <label for="visited_at" class="form-label">Fecha <span class="text-danger">*</span></label>
        <input type="date" class="form-control @error('visited_at') is-invalid @enderror" id="visited_at" name="visited_at" value="{{ old('visited_at', today()->toDateString()) }}" required>
        @error('visited_at')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="visited_at_time" class="form-label">Hora</label>
        <input type="time" class="form-control @error('visited_at_time') is-invalid @enderror" id="visited_at_time" name="visited_at_time" value="{{ old('visited_at_time') }}">
        @error('visited_at_time')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="commitment_date" class="form-label">Compromiso de pago</label>
        <input type="date" class="form-control @error('commitment_date') is-invalid @enderror" id="commitment_date" name="commitment_date" value="{{ old('commitment_date') }}">
        @error('commitment_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="commitment_amount" class="form-label">Monto comprometido</label>
        <div class="input-group has-validation">
            <span class="input-group-text">$</span>
            <input type="number" step="0.01" min="0" class="form-control @error('commitment_amount') is-invalid @enderror" id="commitment_amount" name="commitment_amount" value="{{ old('commitment_amount') }}">
            @error('commitment_amount')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notas de la gestión</label>
        <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" maxlength="1000">{{ old('notes') }}</textarea>
        @error('notes')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
