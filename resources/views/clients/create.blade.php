@extends('layouts.app')

@section('title', 'Nuevo cliente')

@section('content')
    <x-header icon="user-plus" title="Nuevo cliente" text="Registrá la ficha y, si corresponde, el acceso al portal.">
        <x-slot:actions>
            <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver al listado
            </a>
        </x-slot:actions>
    </x-header>

    <form method="POST" action="{{ route('clients.store') }}">
        @csrf

        <div class="row">
            <div class="col-12 col-xl-8">
                <x-card
                    title="Ficha del cliente"
                    subtitle="Los campos marcados con * son obligatorios."
                    icon="id-badge-2"
                    color="primary"
                >
                    @include('clients.partials.form', [
                        'client' => null,
                        'collectors' => $collectors,
                    ])

                    <x-slot:footer>
                        <div class="d-flex justify-content-end flex-wrap gap-2">
                            <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary">Cancelar</a>

                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> Guardar cliente
                            </button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </div>

            <div class="col-12 col-xl-4">
                <x-card title="Antes de guardar" icon="bulb" color="warning">
                    <ul class="small text-secondary mb-0 ps-3">
                        <li class="mb-2">
                            El <strong>teléfono</strong> es obligatorio: se usa para la cobranza y los recordatorios.
                        </li>
                        <li class="mb-2">
                            El <strong>CBU/CVU</strong> debe tener exactamente 22 dígitos. El alias admite letras,
                            números, puntos, guiones y guiones bajos.
                        </li>
                        <li class="mb-2">
                            La <strong>cuenta del portal</strong> es opcional. Si la creás, el email debe ser único
                            y la contraseña tiene que tener al menos 12 caracteres.
                        </li>
                        <li>
                            La asignación a un <strong>cobrador</strong> sólo la puede definir un administrador.
                        </li>
                    </ul>
                </x-card>

                <x-card title="Después del alta" icon="arrow-right-circle" color="info">
                    <p class="small text-secondary mb-2">
                        Con la ficha creada vas a poder:
                    </p>

                    <ul class="small text-secondary mb-0 ps-3">
                        <li>Cargar documentos del cliente.</li>
                        <li>Registrar su primer préstamo.</li>
                        <li>Consultar su estado de cuenta e historial de pagos.</li>
                    </ul>
                </x-card>
            </div>
        </div>
    </form>
@endsection
