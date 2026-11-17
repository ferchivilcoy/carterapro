<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Kredisoft') }} - Acceso al sistema</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])

    </head>
    <body class="antialiased border-top-wide border-primary d-flex flex-column" style="background-image: url({{ asset('img/cover.png') }}); 
            background-size: cover; 
            background-position: center;  
            background-repeat: no-repeat; background-attachment: fixed;">
        @yield('content')
    </body>
</html>
