@extends('layouts.app')

@php
    use App\Services\Money;

    $paidCount = $loan->installments
        ->filter(fn ($installment) => $installment->status === \App\Enums\InstallmentStatus::Paid)
        ->count();
    $remainingCount = $loan->installments->filter(fn ($installment) => $installment->status->isPayable())->count();
    $hasPendingRequest = $loan->refinanceRequests->contains(fn ($request) => $request->isPending());
@endphp

@section('content')
    <x-header icon="file-invoice" title="Préstamo #{{ $loan->id }}" text="{{ $loan->status->label() }} · Inicio {{ $loan->start_date?->format('d/m/Y') }}">
        <x-slot:actions>
            <a href="{{ route('portal.loans') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Mis préstamos
            </a>

            <a href="{{ route('portal.statement') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-file-type-pdf me-1"></i> Estado de cuenta
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="cash-banknote"
                        color="primary"
                        description="Capital"
                        value="{{ Money::format((float) $loan->principal_amount) }}"
                        hint="{{ $loan->frequency?->label() }}"
                    />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="receipt"
                        color="info"
                        description="Total a devolver"
                        value="{{ Money::format((float) $loan->total_amount) }}"
                        hint="{{ $loan->installments_count }} cuota(s)"
                    />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="wallet"
                        color="warning"
                        description="Saldo pendiente"
                        value="{{ Money::format((float) $loan->pending_balance) }}"
                        hint="{{ $remainingCount }} cuota(s) abiertas"
                    />
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat
                        icon="circle-check"
                        color="success"
                        description="Cuotas pagadas"
                        value="{{ $paidCount }} / {{ $loan->installments_count }}"
                        hint="Progreso del plan"
                    />
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-xl-4">
                    <x-card title="Condiciones" icon="clipboard-data" color="primary">
                        <dl class="row small mb-0">
                            <dt class="col-6 text-muted fw-normal">Tasa</dt>
                            <dd class="col-6">{{ (float) $loan->interest_rate }}%</dd>

                            <dt class="col-6 text-muted fw-normal">Tipo de tasa</dt>
                            <dd class="col-6">{{ $loan->rate_type?->label() ?? '—' }}</dd>

                            <dt class="col-6 text-muted fw-normal">Modelo</dt>
                            <dd class="col-6">{{ $loan->amortization_model?->label() ?? '—' }}</dd>

                            <dt class="col-6 text-muted fw-normal">Frecuencia</dt>
                            <dd class="col-6">{{ $loan->frequency?->label() ?? '—' }}</dd>

                            <dt class="col-6 text-muted fw-normal">Entrega</dt>
                            <dd class="col-6">{{ $loan->disbursement_method?->label() ?? '—' }}</dd>

                            <dt class="col-6 text-muted fw-normal">Estado</dt>
                            <dd class="col-6 mb-0"><x-status-badge :status="$loan->status" /></dd>
                        </dl>

                        @if ($loan->notes)
                            <hr>
                            <p class="small text-uppercase text-muted fw-semibold mb-1">Notas</p>
                            <p class="small mb-0" style="white-space: pre-line;">{{ $loan->notes }}</p>
                        @endif
                    </x-card>

                    <x-card title="Refinanciación" icon="repeat" color="warning">
                        @if ($hasPendingRequest)
                            <div class="alert alert-warning small mb-0" role="note">
                                <i class="ti ti-hourglass me-1"></i>
                                Ya enviaste una solicitud para este préstamo. El equipo la va a revisar y se va a
                                comunicar con vos.
                            </div>
                        @elseif ($canRequestRefinance)
                            <p class="small text-muted">
                                Si necesitás reorganizar el pago del saldo pendiente, podés dejar una solicitud.
                                No cambia el préstamo por sí sola: un supervisor la revisa y te contacta.
                            </p>

                            <form method="POST" action="{{ route('portal.loans.refinance-request', $loan) }}">
                                @csrf

                                <div class="mb-3">
                                    <label for="message" class="form-label">Motivo (opcional)</label>
                                    <textarea
                                        class="form-control @error('message') is-invalid @enderror"
                                        id="message"
                                        name="message"
                                        rows="3"
                                        maxlength="500"
                                        placeholder="Contanos brevemente por qué necesitás refinanciar."
                                    >{{ old('message') }}</textarea>
                                    @error('message')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="ti ti-send me-1"></i> Enviar solicitud
                                </button>
                            </form>
                        @else
                            <p class="small text-muted mb-0">
                                Este préstamo no admite solicitudes de refinanciación porque ya está cerrado o sin saldo.
                            </p>
                        @endif

                        @if ($loan->refinanceRequests->isNotEmpty())
                            <hr>
                            <p class="small text-uppercase text-muted fw-semibold mb-2">Solicitudes enviadas</p>

                            @foreach ($loan->refinanceRequests as $request)
                                <div class="d-flex justify-content-between align-items-start gap-2 border-bottom pb-2 mb-2">
                                    <div>
                                        <div class="small text-muted">{{ $request->created_at?->format('d/m/Y H:i') }}</div>
                                        @if ($request->message)
                                            <div class="small">{{ $request->message }}</div>
                                        @endif
                                    </div>
                                    <x-status-badge :status="$request->status" />
                                </div>
                            @endforeach
                        @endif
                    </x-card>
                </div>

                <div class="col-12 col-xl-8">
                    <x-card title="Cuotas del plan" subtitle="{{ $loan->installments->count() }} cuota(s)." icon="calendar-check" color="primary">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Vencimiento</th>
                                        <th class="text-end">Esperado</th>
                                        <th class="text-end">Pagado</th>
                                        <th class="text-end">Saldo</th>
                                        <th class="text-end">Mora</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($loan->installments as $installment)
                                        <tr class="{{ $installment->status->value === 'overdue' ? 'table-danger' : '' }}">
                                            <td class="fw-semibold">#{{ $installment->installment_number }}</td>
                                            <td class="text-nowrap">{{ $installment->due_date?->format('d/m/Y') }}</td>
                                            <td class="text-end text-nowrap">{{ Money::format((float) $installment->expected_amount) }}</td>
                                            <td class="text-end text-nowrap">{{ Money::format((float) $installment->paid_amount) }}</td>
                                            <td class="text-end text-nowrap fw-semibold">{{ Money::format((float) $installment->pending_balance) }}</td>
                                            <td class="text-end text-nowrap">{{ Money::format((float) $installment->late_fee) }}</td>
                                            <td><x-status-badge :status="$installment->status" /></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">
                                                <x-empty-state icon="calendar-check" title="Sin cuotas" text="El plan todavía no tiene cuotas generadas." />
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-card>

                    <x-card title="Pagos del préstamo" subtitle="{{ $loan->payments->count() }} movimiento(s)." icon="cash-coin" color="success">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Recibo</th>
                                        <th>Fecha</th>
                                        <th>Cuota</th>
                                        <th class="text-end">Monto</th>
                                        <th class="text-end">Mora</th>
                                        <th>Método</th>
                                        <th>Estado</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($loan->payments->sortByDesc('payment_date') as $payment)
                                        <tr>
                                            <td class="fw-semibold text-nowrap">{{ $payment->receipt_number }}</td>
                                            <td class="text-nowrap">{{ $payment->payment_date?->format('d/m/Y') }}</td>
                                            <td class="text-nowrap">
                                                {{ $payment->isPrepayment() ? 'Anticipo' : '#'.($payment->installment?->installment_number ?? '—') }}
                                            </td>
                                            <td class="text-end text-nowrap">{{ Money::format((float) $payment->amount) }}</td>
                                            <td class="text-end text-nowrap">{{ Money::format((float) $payment->late_fee_amount) }}</td>
                                            <td>{{ $payment->method?->label() ?? '—' }}</td>
                                            <td><x-status-badge :status="$payment->status" /></td>
                                            <td class="text-end">
                                                <a
                                                    href="{{ route('portal.payments.receipt', $payment) }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    title="Descargar recibo"
                                                >
                                                    <i class="ti ti-download"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8">
                                                <x-empty-state icon="cash-coin" title="Sin pagos registrados" text="Los pagos de este préstamo van a aparecer acá." />
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </div>
            </div>

        </div>
    </div>
@endsection
