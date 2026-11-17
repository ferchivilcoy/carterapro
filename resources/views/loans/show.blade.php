@extends('layouts.app')

@php
    use App\Enums\PaymentMethod;
    use App\Services\Money;

    $canManage = auth()->user()?->can('loans.manage') ?? false;
    $canPay = auth()->user()?->can('payments.manage') ?? false;
    $isEditable = $loan->status->isEditable();
@endphp

@section('content')
    <x-header icon="file-invoice" title="Préstamo #{{ $loan->id }}" text="{{ $loan->client?->name }} · {{ $loan->status->label() }}">
        <x-slot:actions>
            <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver
            </a>

            @can('loans.view')
                <a href="{{ route('print.loans.plan', $loan) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-printer me-1"></i> Imprimir plan
                </a>
            @endcan

            @if ($payable && $canPay && $isEditable)
                <a href="{{ route('payments.create', ['installment_id' => $payable->id]) }}" class="btn btn-success btn-sm">
                    <i class="ti ti-cash-coin me-1"></i> Registrar pago
                </a>
            @endif

            @if ($canManage && $isEditable)
                <a href="{{ route('loans.edit', $loan) }}" class="btn btn-outline-primary btn-sm">
                    <i class="ti ti-pencil me-1"></i> Editar
                </a>

                <div class="dropdown">
                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Más acciones
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#prepayment-modal"><i class="ti ti-wallet me-2"></i> Anticipo a capital</button></li>
                        <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#refinance-modal"><i class="ti ti-repeat me-2"></i> Refinanciar</button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#cancel-modal"><i class="ti ti-circle-x me-2"></i> Cancelar préstamo</button></li>
                    </ul>
                </div>
            @endif
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="cash-banknote" color="primary" description="Capital prestado" value="{{ Money::format((float) $loan->principal_amount) }}" hint="{{ $loan->start_date?->format('d/m/Y') }}" />
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="receipt" color="info" description="Total a devolver" value="{{ Money::format((float) $loan->total_amount) }}" hint="{{ $loan->installments_count }} cuota(s)" />
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="wallet" color="warning" description="Saldo pendiente" value="{{ Money::format((float) $loan->pending_balance) }}" hint="{{ $remainingCount }} cuota(s) abiertas" />
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-stat icon="circle-check" color="success" description="Cuotas pagadas" value="{{ $paidCount }} / {{ $loan->installments_count }}" hint="Progreso del plan" />
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-xl-4">
                    <x-card title="Datos del préstamo" icon="clipboard-data" color="primary">
                        <dl class="row small mb-0">
                            <dt class="col-5 text-muted fw-normal">Cliente</dt>
                            <dd class="col-7">
                                @if ($loan->client)
                                    <a href="{{ route('clients.show', $loan->client) }}" class="text-decoration-none">{{ $loan->client->name }}</a>
                                @else
                                    —
                                @endif
                            </dd>

                            <dt class="col-5 text-muted fw-normal">Documento</dt>
                            <dd class="col-7">{{ $loan->client?->document ?? '—' }}</dd>

                            <dt class="col-5 text-muted fw-normal">Tasa</dt>
                            <dd class="col-7">{{ (float) $loan->interest_rate }}% · {{ $loan->rate_type->label() }}</dd>

                            <dt class="col-5 text-muted fw-normal">Modelo</dt>
                            <dd class="col-7">{{ $loan->amortization_model->label() }}</dd>

                            <dt class="col-5 text-muted fw-normal">Frecuencia</dt>
                            <dd class="col-7">{{ $loan->frequency->label() }}</dd>

                            <dt class="col-5 text-muted fw-normal">Entrega</dt>
                            <dd class="col-7">{{ $loan->disbursement_method?->label() ?? '—' }}</dd>

                            @if ($loan->disbursement_account)
                                <dt class="col-5 text-muted fw-normal">Cuenta destino</dt>
                                <dd class="col-7 text-break">{{ $loan->disbursement_account }}</dd>
                            @endif

                            <dt class="col-5 text-muted fw-normal">Estado</dt>
                            <dd class="col-7"><x-status-badge :status="$loan->status" /></dd>

                            @if ($loan->parentRefinance)
                                <dt class="col-5 text-muted fw-normal">Refinancia a</dt>
                                <dd class="col-7">
                                    <a href="{{ route('loans.show', $loan->parentRefinance) }}" class="text-decoration-none">Préstamo #{{ $loan->parentRefinance->id }}</a>
                                </dd>
                            @endif

                            <dt class="col-5 text-muted fw-normal">Alta</dt>
                            <dd class="col-7">{{ $loan->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </dl>

                        @if ($loan->notes)
                            <hr>
                            <p class="small text-uppercase text-muted fw-semibold mb-1">Notas</p>
                            <p class="small mb-0" style="white-space: pre-line;">{{ $loan->notes }}</p>
                        @endif
                    </x-card>

                    <x-card title="Solicitudes de refinanciación" icon="arrow-left-right" color="warning">
                        @forelse ($loan->refinanceRequests as $refinanceRequest)
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="small fw-semibold">{{ $refinanceRequest->requester?->name ?? 'Portal del cliente' }}</div>
                                        <div class="small text-muted">{{ $refinanceRequest->created_at?->format('d/m/Y H:i') }}</div>
                                    </div>
                                    <x-status-badge :status="$refinanceRequest->status" />
                                </div>

                                @if ($refinanceRequest->message)
                                    <p class="small text-muted mt-2 mb-2">{{ $refinanceRequest->message }}</p>
                                @endif

                                @if ($refinanceRequest->isPending() && $canManage)
                                    <form method="POST" action="{{ route('loans.refinance-requests.attend', [$loan, $refinanceRequest]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="ti ti-check me-1"></i> Marcar como atendida
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <x-empty-state icon="arrow-left-right" title="Sin solicitudes" text="El cliente no pidió refinanciar este préstamo." />
                        @endforelse
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
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($loan->installments as $installment)
                                        <tr class="{{ $installment->status->value === 'overdue' ? 'table-danger' : '' }}">
                                            <td class="fw-semibold">#{{ $installment->installment_number }}</td>
                                            <td class="text-nowrap">
                                                {{ $installment->due_date?->format('d/m/Y') }}
                                                @if ($installment->status->value === 'overdue')
                                                    <span class="badge text-bg-danger ms-1">{{ $installment->daysOverdue() }} d</span>
                                                @endif
                                            </td>
                                            <td class="text-end text-nowrap">{{ Money::format((float) $installment->expected_amount) }}</td>
                                            <td class="text-end text-nowrap">{{ Money::format((float) $installment->paid_amount) }}</td>
                                            <td class="text-end text-nowrap fw-semibold">{{ Money::format((float) $installment->pending_balance) }}</td>
                                            <td class="text-end text-nowrap {{ (float) $installment->late_fee > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ Money::format((float) $installment->late_fee) }}</td>
                                            <td><x-status-badge :status="$installment->status" /></td>
                                            <td class="text-end">
                                                @if ($canPay && $isEditable && $installment->status->isPayable())
                                                    <a href="{{ route('payments.create', ['installment_id' => $installment->id]) }}" class="btn btn-sm btn-outline-success" title="Registrar pago">
                                                        <i class="ti ti-cash-coin"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8">
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
                                            <td class="fw-semibold">{{ $payment->receipt_number }}</td>
                                            <td class="text-nowrap">{{ $payment->payment_date?->format('d/m/Y') }}</td>
                                            <td class="text-nowrap">{{ $payment->installment?->installment_number ?? 'Prepago' }}</td>
                                            <td class="text-end text-nowrap">{{ Money::format((float) $payment->amount) }}</td>
                                            <td class="text-end text-nowrap">{{ Money::format((float) $payment->late_fee_amount) }}</td>
                                            <td>{{ $payment->method->label() }}</td>
                                            <td><x-status-badge :status="$payment->status" /></td>
                                            <td class="text-end">
                                                @can('payments.view')
                                                    <div class="d-flex justify-content-end gap-1">
                                                        <a href="{{ route('payments.show', $payment) }}" class="btn btn-sm btn-outline-secondary" title="Ver pago">
                                                            <i class="ti ti-eye"></i>
                                                        </a>
                                                        <a href="{{ route('print.payments.receipt', $payment) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Imprimir recibo (PDF)">
                                                            <i class="ti ti-printer"></i>
                                                        </a>
                                                    </div>
                                                @endcan
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

                    <x-card title="Bitácora del préstamo" subtitle="Últimos eventos registrados." icon="activity" color="secondary">
                        @forelse ($loan->events->sortByDesc('created_at')->take(10) as $event)
                            <div class="d-flex gap-3 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-secondary-subtle text-secondary-emphasis flex-shrink-0" style="width: 2rem; height: 2rem;">
                                    <i class="ti ti-activity"></i>
                                </span>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between gap-2">
                                        <span class="small fw-semibold">{{ $event->event_type?->label() ?? 'Evento' }}</span>
                                        <span class="small text-muted text-nowrap">{{ $event->created_at?->format('d/m/Y H:i') }}</span>
                                    </div>
                                    <p class="small text-muted mb-0">{{ $event->description }}</p>
                                </div>
                            </div>
                        @empty
                            <x-empty-state icon="history" title="Sin actividad" text="Todavía no hay eventos para este préstamo." />
                        @endforelse
                    </x-card>
                </div>
            </div>

            @if ($canManage && $isEditable)
                <div class="modal fade" id="cancel-modal" tabindex="-1" aria-labelledby="cancel-modal-title" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('loans.cancel', $loan) }}">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title" id="cancel-modal-title">Cancelar préstamo #{{ $loan->id }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="small text-muted">La cancelación anula todas las cuotas abiertas y deja el préstamo fuera de la cartera. No se puede deshacer.</p>
                                    <label for="cancellation_reason" class="form-label">Motivo <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" minlength="5" maxlength="500" required></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                                    <button type="submit" class="btn btn-danger">Cancelar préstamo</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="prepayment-modal" tabindex="-1" aria-labelledby="prepayment-modal-title" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('loans.prepayment', $loan) }}">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title" id="prepayment-modal-title">Anticipo a capital</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-light border small mb-3">
                                        Saldo pendiente actual: <span class="fw-semibold">{{ Money::format((float) $loan->pending_balance) }}</span>.
                                        El anticipo debe ser menor a ese saldo.
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-12 col-md-6">
                                            <label for="prepayment_amount" class="form-label">Monto <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0.01" max="{{ max(0, (float) $loan->pending_balance - 0.01) }}" class="form-control" id="prepayment_amount" name="amount" required>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label for="prepayment_date" class="form-label">Fecha <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="prepayment_date" name="payment_date" value="{{ today()->toDateString() }}" required>
                                        </div>
                                        <div class="col-12">
                                            <label for="prepayment_method" class="form-label">Método <span class="text-danger">*</span></label>
                                            <select class="form-select" id="prepayment_method" name="method" required>
                                                @foreach (PaymentMethod::cases() as $method)
                                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label for="prepayment_notes" class="form-label">Notas</label>
                                            <textarea class="form-control" id="prepayment_notes" name="notes" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Registrar anticipo</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="refinance-modal" tabindex="-1" aria-labelledby="refinance-modal-title" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('loans.refinance', $loan) }}">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title" id="refinance-modal-title">Refinanciar préstamo #{{ $loan->id }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-warning small">
                                        El nuevo préstamo nace por el saldo pendiente de <span class="fw-semibold">{{ Money::format($refinanceBalance) }}</span>. El préstamo actual queda refinanciado y sus cuotas abiertas se anulan.
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-12 col-md-4">
                                            <label for="refinance_interest_rate" class="form-label">Tasa (%) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="refinance_interest_rate" name="interest_rate" value="{{ old('interest_rate', (float) $loan->interest_rate) }}" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label for="refinance_rate_type" class="form-label">Tipo de tasa <span class="text-danger">*</span></label>
                                            <select class="form-select" id="refinance_rate_type" name="rate_type" required>
                                                @foreach ($rateTypes as $rateType)
                                                    <option value="{{ $rateType->value }}" @selected($loan->rate_type === $rateType)>{{ $rateType->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label for="refinance_model" class="form-label">Modelo <span class="text-danger">*</span></label>
                                            <select class="form-select" id="refinance_model" name="amortization_model" required>
                                                @foreach ($amortizationModels as $model)
                                                    <option value="{{ $model->value }}" @selected($loan->amortization_model === $model)>{{ $model->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label for="refinance_installments" class="form-label">Cuotas <span class="text-danger">*</span></label>
                                            <input type="number" min="1" max="120" class="form-control" id="refinance_installments" name="installments_count" value="{{ old('installments_count', $loan->installments_count) }}" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label for="refinance_frequency" class="form-label">Frecuencia <span class="text-danger">*</span></label>
                                            <select class="form-select" id="refinance_frequency" name="frequency" required>
                                                @foreach ($frequencies as $frequency)
                                                    <option value="{{ $frequency->value }}" @selected($loan->frequency === $frequency)>{{ $frequency->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label for="refinance_start_date" class="form-label">Inicio <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="refinance_start_date" name="start_date" value="{{ today()->toDateString() }}" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label for="refinance_disbursement_method" class="form-label">Entrega <span class="text-danger">*</span></label>
                                            <select class="form-select" id="refinance_disbursement_method" name="disbursement_method" required>
                                                @foreach ($disbursementMethods as $method)
                                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-8">
                                            <label for="refinance_disbursement_account" class="form-label">Cuenta de destino</label>
                                            <select class="form-select" id="refinance_disbursement_account" name="disbursement_account">
                                                <option value="">Seleccioná una cuenta</option>
                                                @if ($clientAccount)
                                                    <option value="{{ $clientAccount['value'] }}" @selected(old('disbursement_account') === $clientAccount['value'])>{{ $clientAccount['label'] }}</option>
                                                @endif
                                            </select>
                                            @if (! $clientAccount)
                                                <div class="form-text text-warning">El cliente no tiene datos bancarios cargados.</div>
                                            @endif
                                        </div>
                                        <div class="col-12">
                                            <label for="refinance_notes" class="form-label">Notas</label>
                                            <textarea class="form-control" id="refinance_notes" name="notes" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Refinanciar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection
