@extends('layouts.app')

@section('content')
    <x-header icon="user-cog" title="Mi perfil" text="Datos de tu cuenta y de contacto.">
        <x-slot:actions>
            <a href="{{ route('portal.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Mi cuenta
            </a>
        </x-slot:actions>
    </x-header>
    <div class="page-body">
        <div class="container-xl">

            @if ($errors->any())
                <div class="alert alert-danger d-flex gap-2" role="alert">
                    <i class="ti ti-alert-triangle flex-shrink-0"></i>

                    <div>
                        <p class="fw-semibold mb-1">Revisá los datos ingresados:</p>
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="row">
                <div class="col-12 col-xl-6">
                    <x-card title="Datos de la cuenta" icon="id-badge" color="primary">
                        <form method="POST" action="{{ route('portal.profile.update') }}">
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="name" class="form-label">Nombre y apellido <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        class="form-control @error('name') is-invalid @enderror"
                                        id="name"
                                        name="name"
                                        value="{{ old('name', $user->name) }}"
                                        maxlength="150"
                                        required
                                    >
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label for="profile_email" class="form-label">Email de acceso <span class="text-danger">*</span></label>
                                    <input
                                        type="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        id="profile_email"
                                        name="email"
                                        value="{{ old('email', $user->email) }}"
                                        maxlength="190"
                                        required
                                    >
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label for="current_password" class="form-label">Contraseña actual</label>
                                    <input
                                        type="password"
                                        class="form-control @error('current_password') is-invalid @enderror"
                                        id="current_password"
                                        name="current_password"
                                        autocomplete="current-password"
                                    >
                                    <div class="form-text">Sólo hace falta si vas a cambiar la contraseña.</div>
                                    @error('current_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="password" class="form-label">Nueva contraseña</label>
                                    <input
                                        type="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        id="password"
                                        name="password"
                                        autocomplete="new-password"
                                    >
                                    <div class="form-text">Mínimo 12 caracteres.</div>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="password_confirmation" class="form-label">Repetir nueva contraseña</label>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        autocomplete="new-password"
                                    >
                                </div>
                            </div>

                            <div class="d-flex justify-content-end border-top pt-3 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-check me-1"></i> Guardar cuenta
                                </button>
                            </div>
                        </form>
                    </x-card>
                </div>

                <div class="col-12 col-xl-6">
                    @if ($client)
                        <x-card title="Datos de contacto" icon="phone" color="info">
                            <form method="POST" action="{{ route('portal.contact.update') }}">
                                @csrf
                                @method('PUT')

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label for="phone" class="form-label">Teléfono <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            class="form-control @error('phone') is-invalid @enderror"
                                            id="phone"
                                            name="phone"
                                            value="{{ old('phone', $client->phone) }}"
                                            maxlength="30"
                                            required
                                        >
                                        @error('phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <label for="contact_email" class="form-label">Email de contacto</label>
                                        <input
                                            type="email"
                                            class="form-control @error('email') is-invalid @enderror"
                                            id="contact_email"
                                            name="email"
                                            value="{{ old('email', $client->email) }}"
                                            maxlength="190"
                                        >
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label for="address" class="form-label">Domicilio</label>
                                        <input
                                            type="text"
                                            class="form-control @error('address') is-invalid @enderror"
                                            id="address"
                                            name="address"
                                            value="{{ old('address', $client->address) }}"
                                            maxlength="255"
                                        >
                                        @error('address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end border-top pt-3 mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-check me-1"></i> Guardar contacto
                                    </button>
                                </div>
                            </form>
                        </x-card>

                        <x-card title="Ficha del cliente" icon="clipboard-data" color="secondary">
                            <dl class="row small mb-0">
                                <dt class="col-5 text-muted fw-normal">Nombre</dt>
                                <dd class="col-7">{{ $client->name }}</dd>

                                <dt class="col-5 text-muted fw-normal">Documento</dt>
                                <dd class="col-7">{{ $client->document ?: 'No informado' }}</dd>

                                <dt class="col-5 text-muted fw-normal">Estado</dt>
                                <dd class="col-7"><x-status-badge :status="$client->status" /></dd>

                                <dt class="col-5 text-muted fw-normal">Alta</dt>
                                <dd class="col-7 mb-0">{{ $client->created_at?->format('d/m/Y') }}</dd>
                            </dl>
                        </x-card>
                    @else
                        <x-card title="Datos de contacto" icon="link" color="warning">
                            <p class="small text-muted mb-0">
                                Tu cuenta todavía no está vinculada a una ficha de cliente. Cuando la oficina la asocie
                                vas a poder actualizar tu teléfono, email y domicilio desde acá.
                            </p>
                        </x-card>
                    @endif
                </div>
            </div>

        </div>
    </div>
@endsection
