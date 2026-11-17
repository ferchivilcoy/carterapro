@php
    $isEdit = isset($user) && $user !== null;
    $isSelf = $isEdit && auth()->id() === $user->id;
@endphp

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

<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="name" class="form-label">Nombre y apellido <span class="text-danger">*</span></label>
        <input
            type="text"
            class="form-control @error('name') is-invalid @enderror"
            id="name"
            name="name"
            value="{{ old('name', $user?->name) }}"
            maxlength="150"
            required
            autofocus
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
        <input
            type="email"
            class="form-control @error('email') is-invalid @enderror"
            id="email"
            name="email"
            value="{{ old('email', $user?->email) }}"
            maxlength="190"
            required
        >
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="role" class="form-label">Rol <span class="text-danger">*</span></label>
        <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
            <option value="">Seleccioná un rol</option>
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', $user?->role?->value) === $role->value)>
                    {{ $role->label() }}
                </option>
            @endforeach
        </select>
        @error('role')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @if (auth()->user()?->isSuperAdmin())
        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="is_super_admin" value="0">
                <input
                    type="checkbox"
                    class="form-check-input @error('is_super_admin') is-invalid @enderror"
                    id="is_super_admin"
                    name="is_super_admin"
                    value="1"
                    @checked((bool) old('is_super_admin', $user?->is_super_admin ?? false))
                >
                <label for="is_super_admin" class="form-check-label">Superusuario</label>
                <div class="form-text">
                    Puede activar y desactivar módulos del sistema. Requiere el rol Administrador General.
                </div>
                @error('is_super_admin')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            @if ($isSelf)
                <div class="form-text text-warning mt-1">
                    No podés quitarte la condición de superusuario a vos mismo.
                </div>
            @endif
        </div>
    @endif

    <div class="col-12 col-md-6">
        <label for="password" class="form-label">
            Contraseña
            @unless ($isEdit)
                <span class="text-danger">*</span>
            @endunless
        </label>
        <input
            type="password"
            class="form-control @error('password') is-invalid @enderror"
            id="password"
            name="password"
            autocomplete="new-password"
            @unless ($isEdit) required @endunless
        >
        <div class="form-text">
            {{ $isEdit ? 'Dejar en blanco para conservar la actual.' : 'Mínimo 12 caracteres.' }}
        </div>
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="password_confirmation" class="form-label">Repetir contraseña</label>
        <input
            type="password"
            class="form-control"
            id="password_confirmation"
            name="password_confirmation"
            autocomplete="new-password"
            @unless ($isEdit) required @endunless
        >
    </div>

    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="active" value="0">
            <input
                type="checkbox"
                class="form-check-input @error('active') is-invalid @enderror"
                id="active"
                name="active"
                value="1"
                @checked((bool) old('active', $user?->active ?? true))
            >
            <label for="active" class="form-check-label">Cuenta activa</label>
            <div class="form-text">Una cuenta inactiva no puede iniciar sesión, pero conserva su historial.</div>
            @error('active')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

@if ($isSelf)
    <div class="alert alert-light border small mt-4 mb-0" role="note">
        <i class="ti ti-info-circle text-primary me-1"></i>
        Estás editando tu propia cuenta. Si cambiás el rol o la contraseña, se cerrarán tus otras sesiones.
    </div>
@endif
