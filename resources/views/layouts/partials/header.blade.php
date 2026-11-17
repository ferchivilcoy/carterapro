@php
    $user = auth()->user();
@endphp

<header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbar-menu"
            aria-controls="navbar-menu"
            aria-expanded="false"
            aria-label="Mostrar navegación principal"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="navbar-brand navbar-brand-autodark pe-0 pe-md-3">
            <a href="{{ $user?->homeRoute() ?? route('login') }}" aria-label="{{ config('app.name', 'Kredisoft') }}">
                <img
                    src="{{ asset('img/logo.png') }}"
                    alt="{{ config('app.name', 'Kredisoft') }}"
                    class="navbar-brand-image"
                >
            </a>
        </div>

        @auth
            <div class="navbar-nav flex-row order-md-last">
                <div class="nav-item dropdown">
                    <a
                        href="#"
                        class="nav-link d-flex lh-1 p-0 px-2"
                        data-bs-toggle="dropdown"
                        aria-label="Abrir menú de usuario"
                        aria-expanded="false"
                    >
                        <x-avatar :name="$user->name" size="sm" />

                        <div class="d-none d-xl-block ps-2">
                            <div>{{ $user->name }}</div>
                            <div class="mt-1 small text-secondary">{{ $user->role?->label() }}</div>
                        </div>
                    </a>

                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                        @if ($user->role === \App\Enums\Role::Cliente)
                            <a class="dropdown-item" href="{{ route('portal.index') }}">
                                <i class="ti ti-home me-2" aria-hidden="true"></i>
                                Mi cuenta
                            </a>

                            <a class="dropdown-item" href="{{ route('portal.profile') }}">
                                <i class="ti ti-user-cog me-2" aria-hidden="true"></i>
                                Mi perfil
                            </a>
                        @else
                            <a class="dropdown-item" href="{{ route('dashboard') }}">
                                <i class="ti ti-layout-dashboard me-2" aria-hidden="true"></i>
                                Escritorio
                            </a>

                            @can('settings.manage')
                                <a class="dropdown-item" href="{{ route('settings.index') }}">
                                    <i class="ti ti-settings me-2" aria-hidden="true"></i>
                                    Configuración
                                </a>
                            @endcan
                        @endif

                        <div class="dropdown-divider"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <button type="submit" class="dropdown-item">
                                <i class="ti ti-logout me-2" aria-hidden="true"></i>
                                Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endauth
    </div>
</header>
