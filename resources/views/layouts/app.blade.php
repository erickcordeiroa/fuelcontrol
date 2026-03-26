<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased text-fleet-ink">
        <div class="flex min-h-screen bg-fleet-page">
            @include('layouts.partials.fleet-sidebar')

            <div class="flex min-w-0 flex-1 flex-col">
                @include('layouts.partials.fleet-topbar')

                @isset($header)
                    <header class="border-b border-fleet-border bg-fleet-card">
                        <div class="px-4 py-5 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1 px-4 py-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
