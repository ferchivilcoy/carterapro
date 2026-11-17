@extends('layouts.app')

@section('content')
    <x-header icon="user-exclamation" title="Mi cuenta" text="Tu acceso al portal de clientes.">
        <x-slot:actions>
            <a href="{{ route('portal.profile') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-user-cog me-1"></i> Mi perfil
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <div class="row justify-content-center">
                <div class="col-12 col-lg-8">
                    <x-card title="Cuenta sin vincular" icon="link" color="warning">
                        <p class="mb-3">
                            Tu cuenta de acceso todavía no está vinculada a una ficha de cliente, por eso no podemos
                            mostrarte préstamos, cuotas ni pagos.
                        </p>

                        <p class="small text-muted mb-3">
                            Comunicate con la oficina para que asocien tu cuenta con tu ficha. Una vez vinculada,
                            vas a poder consultar tu estado de cuenta y descargar tus comprobantes.
                        </p>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('portal.profile') }}" class="btn btn-primary">
                                <i class="ti ti-user-cog me-1"></i> Revisar mis datos
                            </a>
                        </div>
                    </x-card>
                </div>
            </div>

        </div>
    </div>
@endsection
