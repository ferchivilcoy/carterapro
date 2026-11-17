<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-layout="fluid">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@hasSection('title')@yield('title') · @endif{{ config('app.name', 'Kredisoft') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @stack('css')
    </head>
    <body>
        <div class="page">
            @include('layouts.partials.header')

            @include('layouts.partials.navbar')

            <div class="page-wrapper">
                <main id="content" class="d-flex flex-column flex-fill">
                    @yield('content')
                </main>
            </div>
        </div>

        @sweetAlert

        @stack('scripts')
    </body>
</html>
