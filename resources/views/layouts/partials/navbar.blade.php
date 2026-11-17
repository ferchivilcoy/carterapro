@php
    $isGestion = request()->routeIs('clients.*', 'loans.*', 'installments.*', 'payments.*', 'collection-visits.*');
    $isDocumentos = request()->routeIs('contracts.*', 'client-documents.*');
    $isAnalisis = request()->routeIs('reports.*', 'profitability.*');
    $isSistema = request()->routeIs('settings.*', 'users.*', 'modules.*');
@endphp

<div class="navbar-expand-md">
    <div class="collapse navbar-collapse" id="navbar-menu">
        <div class="navbar">
            <div class="container-xl">
                <div class="row flex-column flex-md-row flex-fill align-items-center">
                    <div class="col">
                        <nav aria-label="Navegación principal" class="nav-overflow" data-bs-toggle="nav-overflow">
                            <ul class="navbar-nav">
                                @can('dashboard.view')
                                    <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('dashboard') }}">
                                            <span class="nav-link-icon">
                                                <i class="ti ti-home" aria-hidden="true"></i>
                                            </span>
                                            <span class="nav-link-title">Escritorio</span>
                                        </a>
                                    </li>
                                @endcan

                                @canany(['clients.view', 'loans.view', 'installments.view', 'payments.view', 'collection-visits.view'])
                                    <li class="nav-item dropdown {{ $isGestion ? 'active' : '' }}">
                                        <a
                                            class="nav-link dropdown-toggle"
                                            href="#"
                                            data-bs-toggle="dropdown"
                                            data-bs-auto-close="outside"
                                            role="button"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                        >
                                            <span class="nav-link-icon">
                                                <i class="ti ti-briefcase" aria-hidden="true"></i>
                                            </span>
                                            <span class="nav-link-title">Gestión</span>
                                        </a>

                                        <div class="dropdown-menu">
                                            @can('clients.view')
                                                <a class="dropdown-item" href="{{ route('clients.index') }}">
                                                    <i class="ti ti-users me-2" aria-hidden="true"></i>
                                                    Clientes
                                                </a>
                                            @endcan

                                            @can('loans.view')
                                                <a class="dropdown-item" href="{{ route('loans.index') }}">
                                                    <i class="ti ti-file-invoice me-2" aria-hidden="true"></i>
                                                    Préstamos
                                                </a>
                                            @endcan

                                            @can('installments.view')
                                                <a class="dropdown-item" href="{{ route('installments.index') }}">
                                                    <i class="ti ti-calendar-stats me-2" aria-hidden="true"></i>
                                                    Cuotas
                                                </a>
                                            @endcan

                                            @can('payments.view')
                                                <a class="dropdown-item" href="{{ route('payments.index') }}">
                                                    <i class="ti ti-cash-banknote me-2" aria-hidden="true"></i>
                                                    Cobros
                                                </a>
                                            @endcan

                                            @can('collection-visits.view')
                                                <a class="dropdown-item" href="{{ route('collection-visits.index') }}">
                                                    <i class="ti ti-phone-call me-2" aria-hidden="true"></i>
                                                    Cobranzas
                                                </a>
                                            @endcan
                                        </div>
                                    </li>
                                @endcanany

                                @can('cash-movements.view')
                                    <li class="nav-item {{ request()->routeIs('cash-movements.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('cash-movements.index') }}">
                                            <span class="nav-link-icon">
                                                <i class="ti ti-wallet" aria-hidden="true"></i>
                                            </span>
                                            <span class="nav-link-title">Caja</span>
                                        </a>
                                    </li>
                                @endcan

                                @canany(['contracts.view', 'client-documents.view'])
                                    <li class="nav-item dropdown {{ $isDocumentos ? 'active' : '' }}">
                                        <a
                                            class="nav-link dropdown-toggle"
                                            href="#"
                                            data-bs-toggle="dropdown"
                                            data-bs-auto-close="outside"
                                            role="button"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                        >
                                            <span class="nav-link-icon">
                                                <i class="ti ti-files" aria-hidden="true"></i>
                                            </span>
                                            <span class="nav-link-title">Documentos</span>
                                        </a>

                                        <div class="dropdown-menu">
                                            @can('contracts.view')
                                                <a class="dropdown-item" href="{{ route('contracts.index') }}">
                                                    <i class="ti ti-file-text me-2" aria-hidden="true"></i>
                                                    Contratos
                                                </a>
                                            @endcan

                                            @can('client-documents.view')
                                                <a class="dropdown-item" href="{{ route('client-documents.index') }}">
                                                    <i class="ti ti-folder me-2" aria-hidden="true"></i>
                                                    Documentos de clientes
                                                </a>
                                            @endcan
                                        </div>
                                    </li>
                                @endcanany

                                @canany(['reports.view', 'profitability.view'])
                                    <li class="nav-item dropdown {{ $isAnalisis ? 'active' : '' }}">
                                        <a
                                            class="nav-link dropdown-toggle"
                                            href="#"
                                            data-bs-toggle="dropdown"
                                            data-bs-auto-close="outside"
                                            role="button"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                        >
                                            <span class="nav-link-icon">
                                                <i class="ti ti-chart-bar" aria-hidden="true"></i>
                                            </span>
                                            <span class="nav-link-title">Análisis</span>
                                        </a>

                                        <div class="dropdown-menu">
                                            @can('reports.view')
                                                <a class="dropdown-item" href="{{ route('reports.index') }}">
                                                    <i class="ti ti-layout-dashboard me-2" aria-hidden="true"></i>
                                                    Resumen de reportes
                                                </a>
                                                <a class="dropdown-item" href="{{ route('reports.portfolio') }}">
                                                    <i class="ti ti-chart-pie me-2" aria-hidden="true"></i>
                                                    Cartera
                                                </a>
                                                <a class="dropdown-item" href="{{ route('reports.delinquency') }}">
                                                    <i class="ti ti-alert-triangle me-2" aria-hidden="true"></i>
                                                    Mora
                                                </a>
                                                <a class="dropdown-item" href="{{ route('reports.daily-cash') }}">
                                                    <i class="ti ti-cash me-2" aria-hidden="true"></i>
                                                    Caja diaria
                                                </a>
                                                <a class="dropdown-item" href="{{ route('reports.collections-collector') }}">
                                                    <i class="ti ti-users-group me-2" aria-hidden="true"></i>
                                                    Cobranzas por cobrador
                                                </a>
                                                <a class="dropdown-item" href="{{ route('reports.executive') }}">
                                                    <i class="ti ti-presentation me-2" aria-hidden="true"></i>
                                                    Resumen ejecutivo
                                                </a>
                                            @endcan

                                            @can('profitability.view')
                                                @can('reports.view')
                                                    <div class="dropdown-divider"></div>
                                                @endcan

                                                <a class="dropdown-item" href="{{ route('profitability.index') }}">
                                                    <i class="ti ti-trending-up me-2" aria-hidden="true"></i>
                                                    Rentabilidad
                                                </a>
                                            @endcan
                                        </div>
                                    </li>
                                @endcanany

                                @can('events.view')
                                    <li class="nav-item {{ request()->routeIs('events.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('events.index') }}">
                                            <span class="nav-link-icon">
                                                <i class="ti ti-history" aria-hidden="true"></i>
                                            </span>
                                            <span class="nav-link-title">Bitácora</span>
                                        </a>
                                    </li>
                                @endcan

                                @canany(['settings.manage', 'users.manage', 'modules.manage'])
                                    <li class="nav-item dropdown {{ $isSistema ? 'active' : '' }}">
                                        <a
                                            class="nav-link dropdown-toggle"
                                            href="#"
                                            data-bs-toggle="dropdown"
                                            data-bs-auto-close="outside"
                                            role="button"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                        >
                                            <span class="nav-link-icon">
                                                <i class="ti ti-settings" aria-hidden="true"></i>
                                            </span>
                                            <span class="nav-link-title">Sistema</span>
                                        </a>

                                        <div class="dropdown-menu">
                                            @can('settings.manage')
                                                <a class="dropdown-item" href="{{ route('settings.index') }}">
                                                    <i class="ti ti-adjustments-horizontal me-2" aria-hidden="true"></i>
                                                    Configuración
                                                </a>
                                            @endcan

                                            @can('modules.manage')
                                                <a class="dropdown-item" href="{{ route('modules.index') }}">
                                                    <i class="ti ti-toggle-right me-2" aria-hidden="true"></i>
                                                    Módulos
                                                </a>
                                            @endcan

                                            @can('users.manage')
                                                <a class="dropdown-item" href="{{ route('users.index') }}">
                                                    <i class="ti ti-user-cog me-2" aria-hidden="true"></i>
                                                    Usuarios
                                                </a>
                                            @endcan
                                        </div>
                                    </li>
                                @endcanany

                                @if (auth()->user()?->role === \App\Enums\Role::Cliente)
                                    @can('clients.portal')
                                        <li class="nav-item {{ request()->routeIs('portal.*') ? 'active' : '' }}">
                                            <a class="nav-link" href="{{ route('portal.index') }}">
                                                <span class="nav-link-icon">
                                                    <i class="ti ti-user-circle" aria-hidden="true"></i>
                                                </span>
                                                <span class="nav-link-title">Mi cuenta</span>
                                            </a>
                                        </li>
                                    @endcan
                                @endif
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
