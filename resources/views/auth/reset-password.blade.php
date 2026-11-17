@extends('layouts.auth')

@section('content')
  <main class="page page-center" id="content">
    <div class="container container-tight py-4">
      <div class="text-center mb-4">
        <a href="{{ route('login') }}" aria-label="{{ config('app.name', 'Kredisoft') }}" class="navbar-brand navbar-brand-autodark">
          <div class="d-flex justify-content-center align-items-center mb-1">
            <img
              src="{{ asset('img/logo-k.png') }}"
              alt="{{ config('app.name', 'Kredisoft') }}"
              class="img-fluid"
              style="width: 100px; height: auto; filter: drop-shadow(4px 4px 8px rgba(255, 255, 255, 0.5));"
            >
          </div>
        </a>
      </div>

      <div class="card card-md card-gradient card-gradient-rainbow">
        <div class="card-body">
          <h1 class="h2 text-center mb-2">Elegir contraseña nueva</h1>
          <p class="text-secondary text-center mb-4">
            La contraseña debe tener al menos 12 caracteres.
          </p>

          <form action="{{ route('password.update') }}" method="POST" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}" />

            <div class="mb-3">
              <label class="form-label" for="email">Email</label>
              <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                placeholder="tu@email.com" id="email" autocomplete="email" value="{{ old('email', $email) }}" required />
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label" for="password">Contraseña nueva</label>
              <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                placeholder="Tu contraseña nueva" id="password" autocomplete="new-password" required />
              @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label" for="password_confirmation">Repetir contraseña</label>
              <input type="password" name="password_confirmation" class="form-control"
                placeholder="Repetí la contraseña nueva" id="password_confirmation" autocomplete="new-password" required />
            </div>

            <div class="form-footer">
              <button type="submit" class="btn btn-primary w-100">Guardar contraseña</button>
            </div>
          </form>
        </div>
      </div>

      <div class="text-center text-secondary mt-3">
        <a href="{{ route('login') }}">Volver al inicio de sesión</a>
      </div>
    </div>
  </main>
@endsection
