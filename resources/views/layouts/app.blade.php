<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @include('partials.head.tracking')
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

                <main class="flex-1 px-4 py-8 lg:px-10 lg:py-10">
                    @if ($planWarnings !== [])
                        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-fleet-warning bg-fleet-warning/20 px-4 py-3 text-sm text-fleet-ink" role="status">
                            <p>
                                {{ __('Seu plano está perto do limite:') }}
                                {{ collect($planWarnings)->map(fn ($row) => $row['label'].' ('.$row['used'].' de '.$row['max'].')')->implode(', ') }}.
                            </p>
                            <a href="{{ route('subscription.index') }}" wire:navigate class="fleet-btn--outline fleet-btn--sm">{{ __('Ver assinatura') }}</a>
                        </div>
                    @endif
                    @if ($oilChangeAlerts->isNotEmpty())
                        <div class="mb-6">
                            <x-fleet.oil-change-alert :alerts="$oilChangeAlerts" />
                        </div>
                    @endif
                    {{ $slot }}
                </main>
            </div>
        </div>

        <div
            x-data="{ message: null, timer: null }"
            x-on:plan-limit-reached.window="message = $event.detail.message; clearTimeout(timer); timer = setTimeout(() => message = null, 8000)"
            class="pointer-events-none fixed inset-x-0 bottom-6 z-[80] flex justify-center px-4"
            aria-live="assertive"
        >
            <div x-show="message" x-cloak x-transition class="pointer-events-auto flex max-w-xl items-start gap-4 rounded-2xl bg-fleet-ink px-5 py-4 text-sm text-fleet-on-dark shadow-fleet-card">
                <p x-text="message"></p>
                <a href="{{ route('subscription.index') }}" wire:navigate class="shrink-0 font-medium text-white underline underline-offset-4">{{ __('Ver planos') }}</a>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
