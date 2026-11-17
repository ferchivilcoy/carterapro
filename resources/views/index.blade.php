@php
    use App\Services\Money;
    use Illuminate\Support\Str;

    $monthNames = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $monthLabel = $monthNames[(int) now()->format('n') - 1].' '.now()->format('Y');

    $overdueShare = $pendingBalance > 0
        ? (int) round(($overdueAmount / $pendingBalance) * 100)
        : 0;

    $todayChangeLabel = $incomeTodayChange === null
        ? ''
        : ' ('.($incomeTodayChange > 0 ? '+' : '').$incomeTodayChange.'% vs. ayer)';

    $dueTodayCount = $dueToday->count();
    $upcomingCount = $upcoming->count();
    $overdueListLabel = $overdueInstallmentsCount > $overdue->count()
        ? 'las últimas '.$overdue->count().' de '.number_format($overdueInstallmentsCount, 0, ',', '.')
        : null;

    $showPayments = $enabledModules['payments'] ?? false;
    $showLoans = $enabledModules['loans'] ?? false;
    $showInstallments = $enabledModules['installments'] ?? false;
    $showCollectionVisits = $enabledModules['collection-visits'] ?? false;
    $showEvents = $enabledModules['events'] ?? false;
    $showAttention = $showInstallments || $showLoans || $showCollectionVisits;
@endphp

@extends('layouts.app')

@section('title', 'Escritorio')

@section('content')
    <x-header :pretitle="'Resumen operativo · '.now()->format('d/m/Y')" title="Escritorio">
        <x-slot:actions>
            @can('installments.view')
                <a href="{{ route('installments.index') }}" class="btn btn-outline-secondary d-none d-sm-inline-flex">
                    <i class="ti ti-calendar-stats me-1" aria-hidden="true"></i>
                    Ver cuotas
                </a>
            @endcan

            @can('loans.manage')
                <a href="{{ route('loans.create') }}" class="btn btn-primary">
                    <i class="ti ti-plus me-1" aria-hidden="true"></i>
                    Nuevo préstamo
                </a>
            @endcan
        </x-slot:actions>
    </x-header>

    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards mb-3">
                @if ($showPayments)
                    <div class="col-sm-6 col-xl-3">
                        <x-dashboard.stat
                            label="Ingresos del mes"
                            :value="Money::format($monthIncome)"
                            icon="cash-banknote"
                            color="success"
                            :description="'Hoy: '.Money::format($incomeToday).$todayChangeLabel"
                            :trend="$monthIncomeChange"
                            :trend-label="'vs. mes anterior: '.Money::format($previousMonthIncome)"
                        />
                    </div>
                @endif

                @if ($showLoans)
                    <div class="col-sm-6 col-xl-3">
                        <x-dashboard.stat
                            label="Saldo pendiente"
                            :value="Money::format($pendingBalance)"
                            icon="wallet"
                            color="primary"
                            :description="$activeLoansCount.' préstamos activos en cartera'"
                            :progress="$overdueShare"
                            :progress-label="$overdueShare.'% del saldo está en mora'"
                        />
                    </div>
                @endif

                @if ($showInstallments)
                    <div class="col-sm-6 col-xl-3">
                        <x-dashboard.stat
                            label="Cobros de hoy"
                            :value="Money::format($dueTodayTotal)"
                            icon="calendar-due"
                            color="warning"
                            :description="$dueTodayCount.' cuota'.($dueTodayCount === 1 ? '' : 's').' programada'.($dueTodayCount === 1 ? '' : 's').' para hoy'"
                        />
                    </div>

                    <div class="col-sm-6 col-xl-3">
                        <x-dashboard.stat
                            label="Mora acumulada"
                            :value="Money::format($overdueTotal)"
                            icon="alert-triangle"
                            color="danger"
                            :description="number_format($overdueInstallmentsCount, 0, ',', '.').' cuotas vencidas · '.Money::format($overdueLateFees).' en recargos'"
                        />
                    </div>
                @endif
            </div>

            @if ($showPayments || $showLoans)
                <div class="row row-cards mb-3">
                    @if ($showPayments)
                        <div class="{{ $showLoans ? 'col-xl-8' : 'col-12' }}">
                            <x-card
                                title="Evolución de ingresos"
                                subtitle="Cobros registrados en los últimos 6 meses"
                                icon="chart-bar"
                                color="success"
                                class="h-100"
                            >
                                <x-slot:actions>
                                    <span class="badge bg-success-lt text-success">
                                        {{ Money::format($incomeLast30) }} últimos 30 días
                                    </span>
                                </x-slot:actions>

                                <div class="row align-items-end mb-4">
                                    <div class="col">
                                        <div class="h2 mb-0">{{ Money::format($incomeSixMonthsTotal) }}</div>
                                        <div class="text-secondary">
                                            Acumulado de 6 meses · {{ $monthPaymentsCount }} cobros en {{ $monthLabel }}
                                        </div>
                                    </div>
                                    <div class="col-auto text-end">
                                        <div class="small text-secondary">Promedio mensual</div>
                                        <div class="fw-semibold">{{ Money::format($incomeMonthlyAverage) }}</div>
                                    </div>
                                </div>

                                <x-dashboard.income-chart :series="$incomeSeries" />
                            </x-card>
                        </div>
                    @endif

                    @if ($showLoans)
                        <div class="{{ $showPayments ? 'col-xl-4' : 'col-12' }}">
                            <x-card
                                title="Cartera por estado"
                                subtitle="Distribución de los préstamos registrados"
                                icon="chart-pie"
                                color="primary"
                                class="h-100"
                            >
                                <x-dashboard.status-breakdown :items="$loanStatusRows" :total="$totalLoans" />

                                <x-slot:footer>
                                    @can('loans.view')
                                        <a href="{{ route('loans.index') }}" class="link-primary">
                                            Ver todos los préstamos
                                            <i class="ti ti-arrow-right ms-1" aria-hidden="true"></i>
                                        </a>
                                    @endcan
                                </x-slot:footer>
                            </x-card>
                        </div>
                    @endif
                </div>
            @endif

            @if ($showAttention)
                <div class="row row-cards mb-3">
                    @if ($showInstallments)
                        <div class="{{ $showAttention ? 'col-xl-8' : 'col-12' }}">
                            <x-card
                                title="Bandeja de cobranza"
                        subtitle="Cuotas que requieren seguimiento"
                        icon="inbox"
                        color="warning"
                        flush
                        class="h-100"
                    >
                        <x-slot:actions>
                            @can('installments.view')
                                <a href="{{ route('installments.index') }}" class="btn btn-sm btn-outline-secondary">
                                    Ver todas
                                </a>
                            @endcan
                        </x-slot:actions>

                        <div class="card-body pb-0">
                            <ul class="nav nav-tabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <a
                                        class="nav-link active"
                                        href="#collection-today"
                                        data-bs-toggle="tab"
                                        role="tab"
                                        aria-controls="collection-today"
                                        aria-selected="true"
                                    >
                                        Hoy
                                        <span class="badge bg-warning-lt text-warning ms-1">{{ $dueTodayCount }}</span>
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a
                                        class="nav-link"
                                        href="#collection-overdue"
                                        data-bs-toggle="tab"
                                        role="tab"
                                        aria-controls="collection-overdue"
                                        aria-selected="false"
                                    >
                                        Vencidas
                                        <span class="badge bg-danger-lt text-danger ms-1">{{ $overdueInstallmentsCount }}</span>
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a
                                        class="nav-link"
                                        href="#collection-upcoming"
                                        data-bs-toggle="tab"
                                        role="tab"
                                        aria-controls="collection-upcoming"
                                        aria-selected="false"
                                    >
                                        Próximos 7 días
                                        <span class="badge bg-info-lt text-info ms-1">{{ $upcomingCount }}</span>
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a
                                        class="nav-link"
                                        href="#collection-reminders"
                                        data-bs-toggle="tab"
                                        role="tab"
                                        aria-controls="collection-reminders"
                                        aria-selected="false"
                                    >
                                        Recordatorios
                                        <span class="badge bg-secondary-lt text-secondary ms-1">{{ $remindersCount }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="collection-today" role="tabpanel">
                                <x-dashboard.installments-table
                                    :installments="$dueToday"
                                    empty-title="Sin cobros para hoy"
                                    empty-text="No hay cuotas programadas para el día de hoy."
                                    empty-icon="calendar-check"
                                />
                            </div>

                            <div class="tab-pane fade" id="collection-overdue" role="tabpanel">
                                @if ($overdueListLabel)
                                    <div class="card-body py-2 text-secondary small">
                                        Mostrando {{ $overdueListLabel }} cuotas vencidas.
                                    </div>
                                @endif

                                <x-dashboard.installments-table
                                    :installments="$overdue"
                                    :show-overdue="true"
                                    :show-late-fee="true"
                                    empty-title="No hay cuotas vencidas"
                                    empty-text="La cartera está al día."
                                    empty-icon="circle-check"
                                />
                            </div>

                            <div class="tab-pane fade" id="collection-upcoming" role="tabpanel">
                                <x-dashboard.installments-table
                                    :installments="$upcoming"
                                    empty-title="Sin vencimientos próximos"
                                    empty-text="No hay cuotas por vencer en los próximos 7 días."
                                    empty-icon="calendar-stats"
                                />
                            </div>

                            <div class="tab-pane fade" id="collection-reminders" role="tabpanel">
                                <x-dashboard.installments-table
                                    :installments="$reminders"
                                    :show-overdue="true"
                                    :show-late-fee="true"
                                    empty-title="Sin recordatorios pendientes"
                                    empty-text="No hay cuotas dentro de la ventana de recordatorio."
                                    empty-icon="bell-off"
                                />
                            </div>
                        </div>
                            </x-card>
                        </div>
                    @endif

                    @if ($showAttention)
                        <div class="{{ $showInstallments ? 'col-xl-4' : 'col-12' }}">
                            <x-card
                                title="Requiere atención"
                        subtitle="Pendientes operativos del día"
                        icon="alert-circle"
                        color="warning"
                        flush
                        class="h-100"
                    >
                        <div class="list-group list-group-flush">
                            @can('installments.view')
                                <x-dashboard.attention-item
                                    label="Cuotas vencidas"
                                    :value="$overdueInstallmentsCount"
                                    :description="Money::format($overdueTotal).' con recargos'"
                                    icon="alert-triangle"
                                    color="danger"
                                    :href="route('installments.index', ['period' => 'overdue'])"
                                />

                                <x-dashboard.attention-item
                                    label="Próximos 7 días"
                                    :value="$upcomingCount"
                                    :description="Money::format($upcomingTotal).' por vencer'"
                                    icon="calendar-time"
                                    color="info"
                                    :href="route('installments.index', ['period' => 'upcoming'])"
                                />

                                <x-dashboard.attention-item
                                    label="Recordatorios"
                                    :value="$remindersCount"
                                    :description="Money::format($remindersTotal).' en la ventana de aviso'"
                                    icon="bell"
                                    color="warning"
                                    :href="route('installments.index')"
                                />
                            @endcan

                            @can('loans.view')
                                <x-dashboard.attention-item
                                    label="Refinanciaciones"
                                    :value="$pendingRefinanceCount"
                                    description="solicitudes pendientes de atender"
                                    icon="arrows-exchange"
                                    color="azure"
                                    :href="route('loans.index')"
                                />
                            @endcan

                            @can('collection-visits.view')
                                <x-dashboard.attention-item
                                    label="Cobranzas sin contacto"
                                    :value="$pendingContact"
                                    description="visitas por reintentar"
                                    icon="phone-off"
                                    color="secondary"
                                    :href="route('collection-visits.index')"
                                />
                            @endcan
                        </div>

                        <x-slot:footer>
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <span class="small text-secondary">
                                    Actualizado {{ now()->format('H:i') }}
                                </span>

                                @can('reports.view')
                                    <a href="{{ route('reports.index') }}" class="link-primary small">
                                        Ver reportes
                                    </a>
                                @endcan
                            </div>
                        </x-slot:footer>
                            </x-card>
                        </div>
                    @endif
                </div>
            @endif

            @if ($showEvents || $showLoans)
                <div class="row row-cards mb-3">
                    @if ($showEvents)
                        <div class="{{ $showLoans ? 'col-xl-7' : 'col-12' }}">
                            <x-card
                                title="Actividad reciente"
                        subtitle="Últimos movimientos registrados en el sistema"
                        icon="history"
                        color="secondary"
                        flush
                        class="h-100"
                    >
                        <x-slot:actions>
                            @can('events.view')
                                <a href="{{ route('events.index') }}" class="btn btn-sm btn-outline-secondary">
                                    Ver bitácora
                                </a>
                            @endcan
                        </x-slot:actions>

                        @if ($eventsRecent->isEmpty())
                            <div class="card-body">
                                <x-empty-state
                                    icon="history"
                                    title="Sin actividad registrada"
                                    text="Los movimientos del sistema aparecerán acá."
                                />
                            </div>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach ($eventsRecent as $event)
                                    <x-dashboard.event-item :event="$event" />
                                @endforeach
                            </div>
                        @endif
                            </x-card>
                        </div>
                    @endif

                    @if ($showLoans)
                        <div class="{{ $showEvents ? 'col-xl-5' : 'col-12' }}">
                            <x-card
                                title="Clientes con mayor saldo"
                        subtitle="Top 5 por saldo pendiente en cartera"
                        icon="users"
                        color="azure"
                        flush
                        class="h-100"
                    >
                        @if ($topClientsDebt->isEmpty())
                            <div class="card-body">
                                <x-empty-state
                                    icon="users"
                                    title="Sin clientes con saldo"
                                    text="Cuando existan préstamos activos, verás acá los mayores saldos."
                                />
                            </div>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach ($topClientsDebt as $row)
                                    @php
                                        $client = $row->client;
                                        $clientHref = $client && auth()->user()?->can('clients.view')
                                            ? route('clients.show', $client)
                                            : null;
                                    @endphp

                                    @if ($client)
                                        <{{ $clientHref ? 'a' : 'div' }}
                                            @if ($clientHref) href="{{ $clientHref }}" @endif
                                            class="list-group-item {{ $clientHref ? 'list-group-item-action' : '' }}"
                                        >
                                            <div class="d-flex align-items-center gap-2">
                                                <x-avatar :name="$client->name" size="sm" />

                                                <div class="min-w-0 flex-fill">
                                                    <div class="fw-semibold text-truncate">{{ $client->name }}</div>
                                                    <div class="small text-secondary text-truncate">
                                                        {{ $client->phone ?? 'Sin teléfono' }}
                                                        · {{ $row->active_loans_count }} préstamo{{ $row->active_loans_count === 1 ? '' : 's' }} activo{{ $row->active_loans_count === 1 ? '' : 's' }}
                                                    </div>
                                                </div>

                                                <div class="text-end">
                                                    <div class="fw-semibold">{{ Money::format($row->debt) }}</div>
                                                    <div class="small text-secondary">saldo</div>
                                                </div>
                                            </div>
                                        </{{ $clientHref ? 'a' : 'div' }}>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                            </x-card>
                        </div>
                    @endif
                </div>
            @endif

            @if ($showLoans && $pendingRefinanceRequests->isNotEmpty())
                <div class="row row-cards">
                    <div class="col-12">
                        <x-card
                            title="Solicitudes de refinanciamiento"
                            :subtitle="$pendingRefinanceCount.' solicitud'.($pendingRefinanceCount === 1 ? '' : 'es').' pendiente'.($pendingRefinanceCount === 1 ? '' : 's').' de atender'"
                            icon="arrows-exchange"
                            color="warning"
                            flush
                        >
                            <div class="list-group list-group-flush">
                                @foreach ($pendingRefinanceRequests->take(5) as $request)
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-start gap-2">
                                            <x-avatar :name="$request->client?->name ?? 'Cliente'" size="sm" />

                                            <div class="min-w-0 flex-fill">
                                                <div class="d-flex align-items-center justify-content-between gap-2">
                                                    <div class="fw-semibold text-truncate">
                                                        {{ $request->client?->name ?? 'Cliente sin asignar' }}
                                                    </div>

                                                    <span class="badge bg-warning-lt text-warning flex-shrink-0">
                                                        {{ $request->created_at?->format('d/m/Y H:i') }}
                                                    </span>
                                                </div>

                                                <div class="small text-secondary">
                                                    Préstamo
                                                    @can('loans.view')
                                                        <a href="{{ route('loans.show', $request->loan_id) }}" class="link-primary">
                                                            #{{ $request->loan_id }}
                                                        </a>
                                                    @else
                                                        #{{ $request->loan_id }}
                                                    @endcan
                                                </div>

                                                @if (filled($request->message))
                                                    <div class="small text-body mt-1">
                                                        {{ Str::limit($request->message, 100) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-card>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
