 @extends('layouts.auth')

 @section('content')
  <main class="page page-center" id="content">
      <div class="container container-tight py-4">
        <div class="text-center mb-4">
          <!-- BEGIN NAVBAR LOGO -->
          <a href="." aria-label="Tabler" class="navbar-brand navbar-brand-autodark">
            <div class="d-flex justify-content-center align-items-center mb-1">
    <img
        src="{{ asset('img/logo.png') }}"
        alt="{{ config('app.name', 'Laravel') }}"
        class="img-fluid"
        style="width: 100px; height: auto; filter: drop-shadow(4px 4px 8px rgba(255, 255, 255, 0.5));"
    >
</div>
 </a>
 </div>

 <div class="card card-md card-gradient card-gradient-rainbow">
          <div class="card-body">
            <h1 class="h2 text-center mb-4">Ingresa a la cuenta</h1>

            <form action="{{ route('login.store') }}" method="POST" novalidate>
            	@csrf
              <div class="mb-3">
                <label class="form-label" for="email"> Email </label>

                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="tu@email.com" id="email" autocomplete="email" value="{{ old('email') }}" required />
                @error('email')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
              </div>
              <div class="mb-2">
                <div class="d-flex">
                  <label class="form-label" for="signin-password">Contraseña</label>
                  <a href="{{ route('password.request') }}" class="form-label-description ms-auto">Olvidé la contraseña</a>
                </div>
                <div class="input-group input-group-flat">
                  <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Tu contraseña" autocomplete="current-password" id="signin-password" required />
                  @error('password')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror

                  <span class="input-group-text">
                    <button type="button" class="link-secondary input-group-link border-0 bg-transparent p-0" title="Ver contraseña" aria-label="Show password" aria-pressed="false" data-bs-toggle="tooltip" data-password-toggle="signin-password">
                      <!-- Download SVG icon from http://tabler.io/icons/icon/eye -->
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-toggle-show">
                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                        <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
                      </svg>
                      <!-- Download SVG icon from http://tabler.io/icons/icon/eye-off -->
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-toggle-hide d-none">
                        <path d="M10.585 10.587a2 2 0 0 0 2.829 2.828" />
                        <path d="M16.681 16.673a8.717 8.717 0 0 1 -4.681 1.327c-3.6 0 -6.6 -2 -9 -6c1.272 -2.12 2.712 -3.678 4.32 -4.674m2.86 -1.146a9.055 9.055 0 0 1 1.82 -.18c3.6 0 6.6 2 9 6c-.666 1.11 -1.379 2.067 -2.138 2.87" />
                        <path d="M3 3l18 18" />
                      </svg>
                    </button>
                  </span>
                </div>
              </div>
              <div class="mb-2">
                <label class="form-check">
                  <input type="checkbox" name="remember" value="1" class="form-check-input" @checked(old('remember')) />
                  <span class="form-check-label">Recuérdame</span>
                </label>
              </div>

              <div class="form-footer">
                <button type="submit" class="btn btn-primary w-100"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-login-2">
    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
    <path d="M9 8v-2a2 2 0 0 1 2 -2h7a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-2" />
    <path d="M3 12h13l-3 -3" />
    <path d="M13 15l3 -3" />
</svg> Ingresar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>

 <!-- BEGIN INPUT GROUP PASSWORD TOGGLE -->
    <script>
      if (!window.__tablerPasswordToggleInit) {
        window.__tablerPasswordToggleInit = true
        document.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-password-toggle]')
          if (!btn) return
          const input = document.getElementById(btn.getAttribute('data-password-toggle'))
          if (!input) return
          const showing = input.type === 'password'
          input.type = showing ? 'text' : 'password'
          const label = showing ? 'Hide password' : 'Show password'
          btn.setAttribute('aria-pressed', showing ? 'true' : 'false')
          btn.setAttribute('aria-label', label)
          btn.setAttribute('title', label)
          btn.setAttribute('data-bs-original-title', label)
          btn.querySelector('.icon-toggle-show')?.classList.toggle('d-none', showing)
          btn.querySelector('.icon-toggle-hide')?.classList.toggle('d-none', !showing)
        })
      }
    </script>
    <!-- END INPUT GROUP PASSWORD TOGGLE -->
 @endsection