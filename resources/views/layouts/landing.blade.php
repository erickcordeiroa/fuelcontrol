<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @yield('meta')

        @include('partials.head.tracking')
        @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js'])
    </head>
    <body class="landing-page min-h-[100dvh] font-sans antialiased">
        @yield('content')
    </body>
</html>
