<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet" />

        @include('partials.head.tracking')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-background font-body text-on-background antialiased">
        <main class="flex min-h-screen w-full">
            <section @class([
                'relative z-10 flex w-full flex-col items-center overflow-y-auto bg-fleet-page lg:w-1/2',
                'min-h-screen justify-center px-8 py-8 md:px-12 md:py-10 lg:px-20 lg:py-12' => request()->routeIs('login'),
                'justify-start px-8 pb-10 pt-6 md:px-12 md:pb-12 md:pt-8 lg:px-20 lg:pb-16 lg:pt-10' => ! request()->routeIs('login'),
            ])>
                <div class="flex w-full max-w-md flex-col gap-8">
                    @php
                        $brandName = config('app.name') === 'Laravel' ? 'Kilomo' : config('app.name');
                    @endphp
                    <a href="{{ url('/') }}" class="mx-auto inline-flex items-center gap-3 lg:mx-0">
                        <x-fleet.wordmark class="text-4xl" />
                    </a>
                    {{ $slot }}
                </div>
            </section>

            <section class="relative hidden flex-col justify-between bg-fleet-dark px-14 py-14 text-fleet-on-dark lg:flex lg:w-1/2" aria-label="{{ __('O que o Kilomo faz') }}">
                <div>
                                        <p class="max-w-[14ch] font-display text-[clamp(2.25rem,3.6vw,3.25rem)] font-medium leading-[1.12] tracking-[-0.015em]">
                        {{ __('Frota e entregas no mesmo registro.') }}
                    </p>
                </div>

                <dl class="grid grid-cols-[minmax(0,9rem)_minmax(0,1fr)] border-t border-white/20 text-base">
                    <dt class="border-b border-white/10 py-4 text-sm font-medium text-fleet-on-dark-mute">{{ __('Combustível') }}</dt>
                    <dd class="border-b border-white/10 py-4 leading-relaxed text-fleet-on-dark">{{ __('Abastecimentos, despesas e custo por km de cada viagem e veículo.') }}</dd>
                    <dt class="border-b border-white/10 py-4 text-sm font-medium text-fleet-on-dark-mute">{{ __('Manutenção') }}</dt>
                    <dd class="border-b border-white/10 py-4 leading-relaxed text-fleet-on-dark">{{ __('Trocas de óleo por veículo, com aviso da próxima troca.') }}</dd>
                    <dt class="border-b border-white/10 py-4 text-sm font-medium text-fleet-on-dark-mute">{{ __('Entregas') }}</dt>
                    <dd class="border-b border-white/10 py-4 leading-relaxed text-fleet-on-dark">{{ __('Pedidos importados por planilha e paradas ordenadas pelo menor percurso.') }}</dd>
                    <dt class="py-4 text-sm font-medium text-fleet-on-dark-mute">{{ __('Rotas') }}</dt>
                    <dd class="py-4 leading-relaxed text-fleet-on-dark">{{ __('Cada rota do dia no mapa, com entregas feitas e pendentes.') }}</dd>
                </dl>
            </section>
        </main>
    </body>
</html>
