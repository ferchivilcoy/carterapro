@extends('layouts.app')

@section('content')
    <x-header icon="phone-outgoing" title="Nueva gestión de cobranza" text="Registrá el contacto, el resultado y los compromisos asumidos por el cliente.">
        <x-slot:actions>
            <a href="{{ route('collection-visits.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Volver al listado
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            <form method="POST" action="{{ route('collection-visits.store') }}">
                @csrf

                <div class="row">
                    <div class="col-12 col-xl-8">
                        <x-card title="Datos de la gestión" subtitle="Los campos marcados con * son obligatorios." icon="phone-outgoing" color="primary">
                            @include('collection-visits.partials.form', [
                                'clients' => $clients,
                                'loans' => $loans,
                                'installments' => $installments,
                                'types' => $types,
                                'results' => $results,
                            ])

                            <div class="d-flex justify-content-end flex-wrap gap-2 border-top pt-3 mt-4">
                                <a href="{{ route('collection-visits.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-circle-check me-1"></i> Guardar gestión
                                </button>
                            </div>
                        </x-card>
                    </div>

                    <div class="col-12 col-xl-4">
                        <x-card title="Buenas prácticas" icon="bulb" color="warning">
                            <ul class="small text-muted mb-0 ps-3">
                                <li class="mb-2">Registrá una gestión por cada contacto real: llamada, WhatsApp, visita o mensaje.</li>
                                <li class="mb-2">Si el cliente promete pagar, cargá la <strong>fecha y el monto comprometido</strong>.</li>
                                <li class="mb-2">Usá <strong>Pago informado</strong> cuando el cliente avisa que transfirió; después verificá el comprobante en Pagos.</li>
                                <li>Las gestiones alimentan el tablero de cobranzas y la bitácora del cliente.</li>
                            </ul>
                        </x-card>

                        <x-card title="Canales disponibles" icon="message-dots" color="info">
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($types as $case)
                                    <span class="badge rounded-pill text-bg-{{ $case->color() }}">{{ $case->label() }}</span>
                                @endforeach
                            </div>
                        </x-card>
                    </div>
                </div>
            </form>

        </div>
    </div>
@endsection
