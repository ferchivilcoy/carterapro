@extends('layouts.app')

@section('title', 'Portal no disponible')

@section('content')
    <x-header
        icon="lock-off"
        title="Portal no disponible"
        text="El acceso a Mi cuenta está temporalmente desactivado."
    />

    <div class="page-body">
        <div class="container-xl">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-7">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <span class="avatar avatar-xl bg-warning-lt text-warning rounded mb-3">
                                <i class="ti ti-lock-off fs-1" aria-hidden="true"></i>
                            </span>

                            <h2 class="h2 mb-3">El portal está desactivado por la administración</h2>

                            <p class="text-secondary mb-2">
                                Tus préstamos, cuotas y pagos <strong>siguen registrados</strong>.
                                Vas a poder consultarlos nuevamente cuando el portal se reactive.
                            </p>

                            <p class="text-secondary mb-4">
                                Si necesitás información urgente sobre tu cuenta, comunicate con la oficina.
                            </p>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <button type="submit" class="btn btn-outline-secondary">
                                    <i class="ti ti-logout me-1"></i> Cerrar sesión
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
