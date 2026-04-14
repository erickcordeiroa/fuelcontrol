@php
    $brandName = config('app.name') === 'Laravel' ? 'Fuelly' : config('app.name');
    $pageTitle = $brandName . ' — ' . __('Gestão de combustível e frota');
    $pageDescription = __('Fuelly centraliza diário de bordo, abastecimentos, despesas e relatórios da frota em um só sistema para reduzir custos e aumentar o controle operacional.');
    $pageKeywords = __('fuelly, gestão de frota, controle de combustível, diário de bordo digital, relatório de consumo, custo por km, operação logística');
    $pageUrl = url()->current();
    $pageImage = asset('img/logo.png');
    $chartHeights = [40, 65, 45, 80, 55, 90, 70];
@endphp
@extends('layouts.landing')

@section('meta')
    @include('partials.landing.head.meta', [
        'title' => $pageTitle,
        'description' => $pageDescription,
        'keywords' => $pageKeywords,
        'author' => $brandName,
        'url' => $pageUrl,
        'image' => $pageImage,
    ])
@endsection

@section('content')
    <div class="landing-theme relative">
            <header class="relative z-10 border-b border-slate-200/90 bg-white/90 backdrop-blur-md">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-1 sm:px-6 lg:px-8">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3">
                        <img src="{{ asset('img/logo.png') }}" alt="{{ $brandName }}" class="w-100 max-h-24" />
                    </a>
                    @if (Route::has('login'))
                        @include('partials.landing-nav')
                    @endif
                </div>
            </header>

            <main>
                {{-- Hero --}}
                <section class="landing-grid-bg border-b border-slate-200/80">
                    <div class="mx-auto max-w-6xl px-4 pb-16 pt-12 sm:px-6 sm:pb-20 sm:pt-16 lg:px-8 lg:pb-24 lg:pt-20">
                        <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-14">
                            <div class="text-center lg:text-left">
                                <p class="landing-kicker inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-wider">
                                    <span class="landing-kicker-dot h-2 w-2 rounded-full"></span>
                                    {{ __('Operação sob controle') }}
                                </p>
                                <h1 class="mt-6 text-4xl font-extrabold leading-tight tracking-tight text-slate-900 sm:text-5xl lg:text-[2.65rem]">
                                    {{ __('Cada litro e cada quilômetro contados,') }}
                                    <span class="landing-highlight-text">{{ __('sem planilhas soltas.') }}</span>
                                </h1>
                                <p class="mt-6 max-w-xl text-base leading-relaxed text-slate-600 lg:mx-0">
                                    {{ __('Sua empresa cadastra a frota e a operação: abastecimentos, despesas e rotas no diário de bordo, consumo e custo por km nos relatórios, veículos, motoristas e postos — tudo no mesmo lugar, com visão de gestão.') }}
                                </p>
                                <div class="mt-9 flex flex-wrap items-center justify-center gap-3 lg:justify-start">
                                    @auth
                                        <a
                                            href="{{ route('dashboard') }}"
                                            class="landing-btn-primary inline-flex min-h-[44px] items-center justify-center rounded-xl px-7 text-sm font-semibold shadow-md transition"
                                        >
                                            {{ __('Ir para o painel') }}
                                        </a>
                                    @else
                                        <a
                                            href="{{ route('login') }}"
                                            class="landing-btn-primary inline-flex min-h-[44px] items-center justify-center rounded-xl px-7 text-sm font-semibold shadow-md transition"
                                        >
                                            {{ __('Entrar na plataforma') }}
                                        </a>
                                        @if (Route::has('register'))
                                            <a
                                                href="{{ route('register') }}"
                                                class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-200 bg-white px-7 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50"
                                            >
                                                {{ __('Cadastrar empresa') }}
                                            </a>
                                        @endif
                                    @endauth
                                </div>
                            </div>

                            {{-- Mock painel: estilos mistos Tailwind + classes .landing-* para gráfico --}}
                            <div class="relative mx-auto w-full max-w-md lg:mx-0">
                                <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3">
                                        <div class="flex gap-1.5">
                                            <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                                            <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                                        </div>
                                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Pré-visualização') }}</span>
                                    </div>
                                    <div class="space-y-4 p-5">
                                        <div class="flex items-end justify-between gap-4">
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Consumo médio') }}</p>
                                                <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">3,2 <span class="text-base font-semibold text-slate-500">km/L</span></p>
                                            </div>
                                            <div class="rounded-xl bg-emerald-50 px-3 py-2 text-right ring-1 ring-emerald-100">
                                                <p class="text-[11px] font-semibold text-emerald-800">{{ __('Custo / km') }}</p>
                                                <p class="text-sm font-bold tabular-nums text-emerald-900">R$ 1,45</p>
                                            </div>
                                        </div>
                                        <div class="landing-chart-bar flex items-end gap-1 rounded-xl p-2">
                                            @foreach ($chartHeights as $height)
                                                <div class="landing-bar landing-bar-{{ $height }} flex-1"></div>
                                            @endforeach
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">{{ __('Diário') }}</span>
                                            <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">{{ __('Relatórios') }}</span>
                                            <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">{{ __('Frota') }}</span>
                                        </div>
                                    </div>
                                </div>
                                {{-- Decoração SVG sempre visível --}}
                                <div class="pointer-events-none absolute -bottom-6 -right-4 hidden opacity-90 sm:block" aria-hidden="true">
                                    <svg width="120" height="100" viewBox="0 0 120 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M20 85h75c5.5 0 10-4.5 10-10V35c0-5.5-4.5-10-10-10H45L35 15H20c-5.5 0-10 4.5-10 10v50c0 5.5 4.5 10 10 10z" stroke="#94a3b8" stroke-width="2" fill="#f8fafc"/>
                                        <circle cx="38" cy="85" r="8" fill="#334155"/>
                                        <circle cx="82" cy="85" r="8" fill="#334155"/>
                                        <path d="M95 45h12l8 12v18H95V45z" fill="#dbeafe" stroke="#3b82f6" stroke-width="1.5"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Features --}}
                <section class="border-b border-slate-200/80 bg-white py-16 sm:py-20">
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="mx-auto max-w-2xl text-center">
                            <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">
                                {{ __('O que você ganha no dia a dia') }}
                            </h2>
                            <p class="mt-3 text-slate-600">
                                {{ __('Menos retrabalho, mais visibilidade para a gestão e para o motorista no campo.') }}
                            </p>
                        </div>
                        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                                <div class="landing-icon-primary inline-flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-md">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('Diário de bordo unificado') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                    {{ __('Quilometragem, litros, preço por litro, posto e despesas (pedágio, ajudante, alimentação) em um único registro — igual ao fluxo que o motorista já usa na estrada.') }}
                                </p>
                            </article>
                            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                                <div class="landing-icon-secondary inline-flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-md">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('Relatórios que conferem') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                    {{ __('Consumo médio por viagem, custo de combustível por quilômetro, totais e detalhamento por período — para o administrador auditar números e fechar a conta com segurança.') }}
                                </p>
                            </article>
                            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm ring-1 ring-slate-900/5 sm:col-span-2 lg:col-span-1">
                                <div class="landing-icon-neutral inline-flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-md">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                </div>
                                <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('Cadastros sob controle') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                    {{ __('Veículos, motoristas e postos com referência de preço: dados consistentes no sistema e menos erro na hora de lançar abastecimento.') }}
                                </p>
                            </article>
                        </div>
                    </div>
                </section>

                {{-- Passos + público --}}
                <section class="landing-grid-bg border-b border-slate-200/80 py-16 sm:py-20">
                    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <div class="grid gap-12 lg:grid-cols-2 lg:items-stretch lg:gap-14">
                            <div>
                                <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">
                                    {{ __('Da estrada para a gestão, sem perder o fio') }}
                                </h2>
                                <ul class="mt-8 space-y-5">
                                    <li class="flex gap-4">
                                        <span class="landing-step-badge flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold ring-1">1</span>
                                        <p class="text-slate-700 leading-relaxed">{{ __('O motorista registra a viagem no diário com valores em reais no padrão brasileiro (vírgula decimal).') }}</p>
                                    </li>
                                    <li class="flex gap-4">
                                        <span class="landing-step-badge flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold ring-1">2</span>
                                        <p class="text-slate-700 leading-relaxed">{{ __('Postos cadastrados podem preencher automaticamente o nome e o preço por litro — agilidade com consistência.') }}</p>
                                    </li>
                                    <li class="flex gap-4">
                                        <span class="landing-step-badge flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold ring-1">3</span>
                                        <p class="text-slate-700 leading-relaxed">{{ __('O painel e os relatórios consolidam custo de combustível, outras despesas e desempenho por veículo ou período.') }}</p>
                                    </li>
                                </ul>
                            </div>
                            <div class="landing-card-dark relative overflow-hidden rounded-2xl border border-slate-700/50 p-8 lg:flex lg:flex-col lg:justify-center">
                                <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"></div>
                                <div class="landing-accent-soft pointer-events-none absolute -bottom-8 left-8 h-24 w-24 rounded-full"></div>
                                <p class="landing-accent-title relative text-xs font-bold uppercase tracking-widest">{{ __('Feito para') }}</p>
                                <p class="relative mt-4 text-lg font-semibold leading-snug text-white">
                                    {{ __('Transportadoras, frotas próprias e operações que precisam de rastreabilidade de combustível sem burocracia extra.') }}
                                </p>
                                <p class="relative mt-4 text-sm leading-relaxed text-slate-300">
                                    {{ __('O foco é operação real: números que batem entre o que foi lançado no diário e o que aparece no relatório — para o administrador validar com tranquilidade.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- CTA --}}
                <section class="landing-cta-section py-16">
                    <div class="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
                        <h2 class="text-2xl font-bold text-white sm:text-3xl">{{ __('Pronto para organizar sua frota?') }}</h2>
                        <p class="mx-auto mt-3 max-w-lg text-sm text-slate-200">
                            {{ __('Cadastre sua empresa ou entre com a conta do administrador para gerenciar frota e operação.') }}
                        </p>
                        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                            @auth
                                <a
                                    href="{{ route('dashboard') }}"
                                    class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-white px-8 text-sm font-semibold text-slate-900 shadow-lg transition hover:bg-slate-100"
                                >
                                    {{ __('Abrir o painel') }}
                                </a>
                            @else
                                <a
                                    href="{{ route('register') }}"
                                    class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-white/30 bg-white/10 px-8 text-sm font-semibold text-white shadow-lg transition hover:bg-white/20"
                                >
                                    {{ __('Cadastrar empresa') }}
                                </a>
                                <a
                                    href="{{ route('login') }}"
                                    class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-white px-8 text-sm font-semibold text-slate-900 shadow-lg transition hover:bg-slate-100"
                                >
                                    {{ __('Entrar') }}
                                </a>
                            @endauth
                        </div>
                    </div>
                </section>
            </main>

            <footer class="border-t border-slate-200 bg-white py-10">
                <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 text-center text-sm text-slate-500 sm:flex-row sm:text-left sm:px-6 lg:px-8">
                    <p>&copy; {{ date('Y') }} {{ $brandName }}. {{ __('Todos os direitos reservados.') }}</p>
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="landing-link font-semibold">{{ __('Acesso à plataforma') }}</a>
                    @endif
                </div>
            </footer>
    </div>
@endsection
