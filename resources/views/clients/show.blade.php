@extends('layouts.app')

@section('title', $client->name)

@section('content')
    <x-header icon="id-badge-2" title="{{ $client->name }}" text="{{ $client->document ? 'DNI '.$client->document.' · '.$client->phone : $client->phone }}">
        <x-slot:actions>
            <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver
            </a>

            @can('clients.view')
                <a href="{{ route('print.clients.statement', $client) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-printer me-1"></i> Estado de cuenta
                </a>
            @endcan

            @can('loans.manage')
                <a href="{{ route('loans.create', ['client_id' => $client->id]) }}" class="btn btn-outline-primary btn-sm">
                    <i class="ti ti-file-plus me-1"></i> Nuevo préstamo
                </a>
            @endcan

            @can('clients.manage')
                <a href="{{ route('clients.edit', $client) }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-pencil me-1"></i> Editar
                </a>
            @endcan
        </x-slot:actions>
    </x-header>

    <div class="row">
        <div class="col-12 col-xl-4">
            <x-card title="Datos del cliente" icon="user" color="primary">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <x-avatar :name="$client->name" size="lg" />

                    <div>
                        <p class="h5 fw-semibold mb-1">{{ $client->name }}</p>
                        <div class="d-flex flex-wrap gap-1">
                            <x-status-badge :status="$client->status" />
                            <x-status-badge :status="$client->tier" />
                        </div>
                    </div>
                </div>

                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary fw-normal">Documento</dt>
                    <dd class="col-7">{{ $client->document ?? '—' }}</dd>

                    <dt class="col-5 text-secondary fw-normal">Teléfono</dt>
                    <dd class="col-7">
                        <a href="tel:{{ $client->phone }}" class="text-decoration-none">{{ $client->phone }}</a>
                    </dd>

                    <dt class="col-5 text-secondary fw-normal">Email</dt>
                    <dd class="col-7 text-break">
                        @if ($client->email)
                            <a href="mailto:{{ $client->email }}" class="text-decoration-none">{{ $client->email }}</a>
                        @else
                            —
                        @endif
                    </dd>

                    <dt class="col-5 text-secondary fw-normal">Domicilio</dt>
                    <dd class="col-7">{{ $client->address ?? '—' }}</dd>

                    <dt class="col-5 text-secondary fw-normal">Cobrador</dt>
                    <dd class="col-7">{{ $client->collector?->name ?? 'Sin asignar' }}</dd>

                    <dt class="col-5 text-secondary fw-normal">Score</dt>
                    <dd class="col-7 fw-semibold">{{ $client->current_score }} / 100</dd>

                    <dt class="col-5 text-secondary fw-normal">Alta</dt>
                    <dd class="col-7">{{ $client->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </dl>

                @if ($client->notes)
                    <hr>

                    <p class="small text-uppercase text-secondary fw-semibold mb-1">Notas</p>
                    <p class="small mb-0">{{ $client->notes }}</p>
                @endif
            </x-card>

            <x-card title="Cuenta para pagos" icon="building-bank" color="success">
                @if ($client->accountSummary())
                    <dl class="row small mb-0">
                        <dt class="col-5 text-secondary fw-normal">Banco / billetera</dt>
                        <dd class="col-7">{{ $client->bank_wallet ?? '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">CBU / CVU</dt>
                        <dd class="col-7 text-break">{{ $client->cbu_cvu ?? '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Alias</dt>
                        <dd class="col-7">{{ $client->alias ?? '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Titular</dt>
                        <dd class="col-7">{{ $client->account_holder ?? $client->name }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Tipo</dt>
                        <dd class="col-7">
                            <span class="badge rounded-pill {{ $client->is_own_account ? 'text-bg-success' : 'text-bg-warning' }}">
                                {{ $client->is_own_account ? 'Cuenta propia' : 'Cuenta de un tercero' }}
                            </span>
                        </dd>
                    </dl>
                @else
                    <x-empty-state
                        icon="building-bank"
                        title="Sin datos de cuenta"
                        text="Editá la ficha para cargar CBU/CVU o alias."
                    />
                @endif
            </x-card>

            <x-card title="Portal del cliente" icon="login-2" color="info">
                @if ($portalAccount)
                    <dl class="row small mb-0">
                        <dt class="col-5 text-secondary fw-normal">Email de acceso</dt>
                        <dd class="col-7 text-break">{{ $portalAccount->email }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Estado</dt>
                        <dd class="col-7">
                            <span class="badge rounded-pill {{ $portalAccount->active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $portalAccount->active ? 'Habilitado' : 'Deshabilitado' }}
                            </span>
                        </dd>
                    </dl>
                @else
                    <x-empty-state
                        icon="lock"
                        title="Sin cuenta de portal"
                        text="Podés crearla desde la edición de la ficha."
                    />
                @endif
            </x-card>
        </div>

        <div class="col-12 col-xl-8">
            <div class="row mb-4">
                <div class="col-12 col-md-4">
                    <x-dashboard.stat
                        label="Préstamos activos"
                        :value="number_format($activeLoansCount, 0, ',', '.')"
                        icon="file-text"
                        color="primary"
                        description="En cartera"
                    />
                </div>

                <div class="col-12 col-md-4">
                    <x-dashboard.stat
                        label="Saldo pendiente"
                        :value="\App\Services\Money::format($pendingBalance)"
                        icon="wallet"
                        color="warning"
                        description="Capital + interés"
                    />
                </div>

                <div class="col-12 col-md-4">
                    <x-dashboard.stat
                        label="Total cobrado"
                        :value="\App\Services\Money::format($totalPaid)"
                        icon="cash-banknote"
                        color="success"
                        description="Pagos activos"
                    />
                </div>
            </div>

            <x-card title="Préstamos" subtitle="{{ $client->loans->count() }} registro(s)." icon="file-text" color="primary" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Préstamo</th>
                                <th class="text-end">Monto</th>
                                <th>Cuotas</th>
                                <th class="text-end">Saldo</th>
                                <th>Inicio</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($client->loans as $loan)
                                <tr>
                                    <td class="fw-semibold">#{{ $loan->id }}</td>
                                    <td class="text-end text-nowrap">{{ \App\Services\Money::format((float) $loan->principal_amount) }}</td>
                                    <td class="text-nowrap">
                                        {{ $loan->paid_installments_count }} / {{ $loan->installments_count }}
                                    </td>
                                    <td class="text-end text-nowrap">{{ \App\Services\Money::format((float) $loan->pending_balance) }}</td>
                                    <td class="text-nowrap text-secondary">{{ $loan->start_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td><x-status-badge :status="$loan->status" /></td>
                                    <td class="text-end">
                                        @can('loans.view')
                                            <a
                                                href="{{ route('loans.show', $loan) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Ver préstamo"
                                            >
                                                <i class="ti ti-eye"></i>
                                            </a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <x-empty-state
                                            icon="file-text"
                                            title="Sin préstamos"
                                            text="El cliente todavía no tiene préstamos registrados."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card title="Últimos pagos" subtitle="Se muestran los 10 pagos activos más recientes." icon="cash-coin" color="success" flush>
                <x-slot:actions>
                    @can('payments.manage')
                        <a href="{{ route('payments.create') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ti ti-plus me-1"></i> Registrar pago
                        </a>
                    @endcan
                </x-slot:actions>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Recibo</th>
                                <th>Fecha</th>
                                <th>Préstamo</th>
                                <th>Cuota</th>
                                <th class="text-end">Monto</th>
                                <th>Método</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td class="fw-semibold">{{ $payment->receipt_number }}</td>
                                    <td class="text-nowrap">{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="text-nowrap">#{{ $payment->loan_id }}</td>
                                    <td class="text-nowrap">
                                        {{ $payment->installment?->installment_number ?? 'Prepago' }}
                                    </td>
                                    <td class="text-end text-nowrap">{{ \App\Services\Money::format((float) $payment->amount) }}</td>
                                    <td>{{ $payment->method->label() }}</td>
                                    <td><x-status-badge :status="$payment->status" /></td>
                                    <td class="text-end">
                                        @can('payments.view')
                                            <a
                                                href="{{ route('payments.show', $payment) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Ver pago"
                                            >
                                                <i class="ti ti-eye"></i>
                                            </a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <x-empty-state
                                            icon="cash-coin"
                                            title="Sin pagos registrados"
                                            text="Los pagos del cliente van a aparecer acá."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </div>
@endsection
