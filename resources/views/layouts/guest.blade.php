<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-background font-body text-on-background antialiased">
        <main class="flex min-h-screen w-full">
            <section @class([
                'relative z-10 flex w-full flex-col items-center overflow-y-auto bg-surface-container-lowest lg:w-1/2',
                'min-h-screen justify-center px-8 py-8 md:px-12 md:py-10 lg:px-20 lg:py-12' => request()->routeIs('login'),
                'justify-start px-8 pb-10 pt-6 md:px-12 md:pb-12 md:pt-8 lg:px-20 lg:pb-16 lg:pt-10' => ! request()->routeIs('login'),
            ])>
                <div class="flex w-full max-w-md flex-col gap-8">
                    {{ $slot }}
                </div>
            </section>

            <section class="relative hidden items-center justify-center overflow-hidden bg-surface-container p-12 lg:flex lg:w-1/2">
                <div class="absolute inset-0 bg-gradient-to-br from-primary-container/30 via-tertiary-container/20 to-secondary-container/30"></div>
                <div class="absolute right-[-10%] top-[-10%] h-[500px] w-[500px] rounded-full bg-primary/5 blur-[100px]"></div>
                <div class="absolute bottom-[-10%] left-[-10%] h-[400px] w-[400px] rounded-full bg-secondary/10 blur-[80px]"></div>

                <div class="relative z-20 flex w-full max-w-lg flex-col items-center gap-12">
                    <div class="flex w-full flex-col gap-8">
                        <div class="glass-card hover:translate-x-4 space-y-4 self-end translate-x-8 rounded-3xl border border-white/40 p-6 shadow-2xl transition-transform duration-500">
                            <div class="flex items-start gap-6">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-secondary/10">
                                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1">trending_down</span>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="font-headline font-bold text-on-surface">Consumo Reduzido</h4>
                                        <p class="text-xs text-on-surface-variant">Últimos 30 dias</p>
                                    </div>
                                </div>
                                <span class="shrink-0 pt-0.5 font-headline text-2xl font-extrabold tabular-nums text-secondary">-12.4%</span>
                            </div>
                            <div class="flex h-12 items-end gap-1.5 px-2">
                                <div class="h-[60%] w-full rounded-t-lg bg-secondary-container/40"></div>
                                <div class="h-[45%] w-full rounded-t-lg bg-secondary-container/40"></div>
                                <div class="h-[80%] w-full rounded-t-lg bg-secondary-container/40"></div>
                                <div class="h-[30%] w-full rounded-t-lg bg-secondary-container/40"></div>
                                <div class="h-[65%] w-full rounded-t-lg bg-secondary-container/40"></div>
                                <div class="h-[90%] w-full rounded-t-lg bg-secondary-container/40"></div>
                                <div class="h-[50%] w-full rounded-t-lg bg-secondary-container/40"></div>
                            </div>
                        </div>

                        <div class="glass-card hover:-translate-x-4 space-y-4 self-start -translate-x-8 rounded-3xl border border-white/40 p-6 shadow-2xl transition-transform duration-500">
                            <div class="flex items-start gap-6">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10">
                                        <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1">directions_car</span>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="font-headline font-bold text-on-surface">Frota Ativa</h4>
                                        <p class="text-xs text-on-surface-variant">Em operação agora</p>
                                    </div>
                                </div>
                                <span class="shrink-0 pt-0.5 font-headline text-2xl font-extrabold tabular-nums text-primary">142</span>
                            </div>
                            <div class="flex gap-2">
                                <div class="-space-x-3 flex">
                                    <div class="h-8 w-8 rounded-full border-2 border-white bg-primary-fixed"></div>
                                    <div class="h-8 w-8 rounded-full border-2 border-white bg-secondary-fixed"></div>
                                    <div class="h-8 w-8 rounded-full border-2 border-white bg-tertiary-fixed"></div>
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-white bg-surface-dim text-[10px] font-bold text-on-surface">+139</div>
                                </div>
                                <div class="h-2 flex-1 self-center overflow-hidden rounded-full bg-surface-container-low">
                                    <div class="h-full w-[94%] bg-primary"></div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card hover:translate-x-4 space-y-4 self-end translate-x-8 rounded-3xl border border-white/40 p-6 shadow-2xl transition-transform duration-500">
                            <div class="flex items-start gap-6">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-tertiary/10">
                                        <span class="material-symbols-outlined text-tertiary" style="font-variation-settings: 'FILL' 1">payments</span>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="font-headline font-bold text-on-surface">Economia Mensal</h4>
                                        <p class="text-xs text-on-surface-variant">Projeção estimada</p>
                                    </div>
                                </div>
                                <div class="shrink-0 pt-0.5 text-right">
                                    <span class="font-headline text-2xl font-extrabold tabular-nums text-tertiary">R$ 4.2k</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 pt-2">
                                <div class="rounded-xl border border-white/20 bg-surface-container-low/50 p-2">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Manutenção</p>
                                    <p class="text-sm font-bold text-on-surface">-R$ 840,00</p>
                                </div>
                                <div class="rounded-xl border border-white/20 bg-surface-container-low/50 p-2">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">Combustível</p>
                                    <p class="text-sm font-bold text-on-surface">-R$ 3.360,00</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 text-center">
                        <h2 class="font-headline text-2xl font-bold text-on-surface">Gestão em tempo real</h2>
                        <p class="text-lg font-medium leading-relaxed text-on-surface-variant">
                            Visualize cada veículo, monitore o consumo de combustível e reduza custos operacionais com inteligência preditiva.
                        </p>
                    </div>
                </div>

                <div class="absolute bottom-12 right-12 flex items-center gap-2 rounded-full border border-white/30 bg-white/50 px-4 py-2 text-xs font-bold text-on-surface-variant backdrop-blur-md">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-secondary"></span>
                    Sistemas operacionais: Estáveis
                </div>
            </section>
        </main>
    </body>
</html>
