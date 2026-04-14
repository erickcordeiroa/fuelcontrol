<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @yield('meta')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700;800&display=swap" rel="stylesheet" />

        @include('partials.head.tracking')
        @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js'])
    </head>
    <body class="landing-page min-h-screen antialiased">
        @yield('content')
    </body>
</html>
