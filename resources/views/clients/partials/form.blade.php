@php
    $isEdit = isset($client) && $client !== null;
    $portalAccount = $portalAccount ?? null;
    $collectors = $collectors ?? collect();
    $isAdmin = auth()->user()?->isAdmin() ?? false;
@endphp

@if ($errors->any())
    <div class="alert alert-danger d-flex gap-2" role="alert">
        <i class="ti ti-alert-triangle flex-shrink-0" aria-hidden="true"></i>

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

<h3 class="h6 text-uppercase text-secondary fw-semibold mb-3">Datos personales</h3>

<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="name" class="form-label">Nombre y apellido <span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control @error('name') is-invalid @enderror"
            id="name"
            name="name"
            value="{{ old('name', $client?->name) }}"
            maxlength="150"
            required
            autofocus
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="document" class="form-label">Documento</label>
        <input
            type="text"
            class="form-control @error('document') is-invalid @enderror"
            id="document"
            name="document"
            value="{{ old('document', $client?->document) }}"
            maxlength="30"
        >
        @error('document')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="phone" class="form-label">Teléfono <span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control @error('phone') is-invalid @enderror"
            id="phone"
            name="phone"
            value="{{ old('phone', $client?->phone) }}"
            maxlength="30"
            required
        >
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="email" class="form-label">Email de contacto</label>
        <input
            type="email"
            class="form-control @error('email') is-invalid @enderror"
            id="email"
            name="email"
            value="{{ old('email', $client?->email) }}"
            maxlength="190"
        >
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="address" class="form-label">Domicilio</label>
        <input
            type="text"
            class="form-control @error('address') is-invalid @enderror"
            id="address"
            name="address"
            value="{{ old('address', $client?->address) }}"
            maxlength="255"
        >
        @error('address')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notas internas</label>
        <textarea
            class="form-control @error('notes') is-invalid @enderror"
            id="notes"
            name="notes"
            rows="3"
        >{{ old('notes', $client?->notes) }}</textarea>
        @error('notes')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<hr class="my-4">

<h3 class="h6 text-uppercase text-secondary fw-semibold mb-3">Cuenta para acreditar pagos</h3>

<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="bank_wallet" class="form-label">Banco o billetera</label>
        <input
            type="text"
            class="form-control @error('bank_wallet') is-invalid @enderror"
            id="bank_wallet"
            name="bank_wallet"
            value="{{ old('bank_wallet', $client?->bank_wallet) }}"
            maxlength="100"
            placeholder="Ej.: Mercado Pago, Banco Nación"
        >
        @error('bank_wallet')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="cbu_cvu" class="form-label">CBU / CVU</label>
        <input
            type="text"
            class="form-control @error('cbu_cvu') is-invalid @enderror"
            id="cbu_cvu"
            name="cbu_cvu"
            value="{{ old('cbu_cvu', $client?->cbu_cvu) }}"
            inputmode="numeric"
            maxlength="22"
            placeholder="22 dígitos"
        >
        @error('cbu_cvu')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="alias" class="form-label">Alias</label>
        <input
            type="text"
            class="form-control @error('alias') is-invalid @enderror"
            id="alias"
            name="alias"
            value="{{ old('alias', $client?->alias) }}"
            maxlength="50"
            placeholder="Ej.: juan.perez.mp"
        >
        @error('alias')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="account_holder" class="form-label">Titular de la cuenta</label>
        <input
            type="text"
            class="form-control @error('account_holder') is-invalid @enderror"
            id="account_holder"
            name="account_holder"
            value="{{ old('account_holder', $client?->account_holder) }}"
            maxlength="150"
            placeholder="Ej.: nombre del familiar titular"
        >
        @error('account_holder')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="is_own_account" value="0">
            <input
                type="checkbox"
                class="form-check-input @error('is_own_account') is-invalid @enderror"
                id="is_own_account"
                name="is_own_account"
                value="1"
                @checked(old('is_own_account', $client?->is_own_account ?? true))
            >
            <label for="is_own_account" class="form-check-label">
                La cuenta pertenece al cliente
            </label>
            <div class="form-text">Desmarcá esta opción si la cuenta es de un tercero.</div>
            @error('is_own_account')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<hr class="my-4">

<h3 class="h6 text-uppercase text-secondary fw-semibold mb-3">Acceso al portal del cliente</h3>

<div class="row g-3">
    <div class="col-12">
        <div class="alert alert-light border d-flex gap-2 mb-0" role="note">
            <i class="ti ti-info-circle text-primary flex-shrink-0" aria-hidden="true"></i>
            <div class="small text-secondary">
                @if ($portalAccount)
                    El cliente ya tiene una cuenta de portal creada con el email
                    <span class="fw-semibold">{{ $portalAccount->email }}</span>.
                    Dejá la contraseña en blanco para no cambiarla.
                @else
                    Completá el email y la contraseña para crear la cuenta con la que el cliente
                    podrá consultar sus préstamos y pagos en “Mi cuenta”.
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <label for="portal_email" class="form-label">Email de acceso</label>
        <input
            type="email"
            class="form-control @error('portal_email') is-invalid @enderror"
            id="portal_email"
            name="portal_email"
            value="{{ old('portal_email', $portalAccount?->email) }}"
            maxlength="190"
        >
        @error('portal_email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-4">
        <label for="portal_password" class="form-label">Contraseña</label>
        <input
            type="password"
            class="form-control @error('portal_password') is-invalid @enderror"
            id="portal_password"
            name="portal_password"
            autocomplete="new-password"
        >
        <div class="form-text">
            {{ $isEdit ? 'Dejar en blanco para conservar la actual. Mínimo 12 caracteres.' : 'Mínimo 12 caracteres.' }}
        </div>
        @error('portal_password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-4">
        <label for="portal_password_confirmation" class="form-label">Repetir contraseña</label>
        <input
            type="password"
            class="form-control"
            id="portal_password_confirmation"
            name="portal_password_confirmation"
            autocomplete="new-password"
        >
    </div>

    @if ($isEdit && $portalAccount)
        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="portal_active" value="0">
                <input
                    type="checkbox"
                    class="form-check-input"
                    id="portal_active"
                    name="portal_active"
                    value="1"
                    @checked(old('portal_active', $portalAccount->active))
                >
                <label for="portal_active" class="form-check-label">Cuenta del portal habilitada</label>
            </div>
        </div>
    @endif
</div>

<hr class="my-4">

<h3 class="h6 text-uppercase text-secondary fw-semibold mb-3">Asignación y estado</h3>

<div class="row g-3">
    @if ($isAdmin)
        <div class="col-12 col-md-6">
            <label for="assigned_collector_id" class="form-label">Cobrador asignado</label>
            <select
                class="form-select @error('assigned_collector_id') is-invalid @enderror"
                id="assigned_collector_id"
                name="assigned_collector_id"
            >
                <option value="">Sin asignar</option>
                @foreach ($collectors as $collector)
                    <option
                        value="{{ $collector->id }}"
                        @selected((string) old('assigned_collector_id', $client?->assigned_collector_id) === (string) $collector->id)
                    >{{ $collector->name }}</option>
                @endforeach
            </select>
            <div class="form-text">Sólo los administradores pueden cambiar la asignación.</div>
            @error('assigned_collector_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif

    @if ($isEdit)
        <div class="col-12 col-md-6">
            <label for="status" class="form-label">Estado de la ficha</label>
            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                @foreach (\App\Enums\ClientStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected(old('status', $client?->status?->value) === $case->value)>
                        {{ $case->label() }}
                    </option>
                @endforeach
            </select>
            @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif
</div>
