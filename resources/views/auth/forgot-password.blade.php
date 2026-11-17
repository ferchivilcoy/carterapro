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
          <h1 class="h2 text-center mb-2">Recuperar contraseña</h1>
          <p class="text-secondary text-center mb-4">
            Escribí tu correo y te enviamos un enlace para elegir una contraseña nueva.
          </p>

          @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
          @endif

          <form action="{{ route('password.email') }}" method="POST" novalidate>
            @csrf
            <div class="mb-3">
              <label class="form-label" for="email">Email</label>
              <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                placeholder="tu@email.com" id="email" autocomplete="email" value="{{ old('email') }}" required />
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="form-footer">
              <button type="submit" class="btn btn-primary w-100">Enviar enlace</button>
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
