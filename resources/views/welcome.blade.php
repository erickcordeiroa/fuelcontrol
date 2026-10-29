@php
    use App\Services\Billing\PlanLimits;

    $brandName = config('app.name') === 'Laravel' ? 'Fuelly' : config('app.name');
    $pageTitle = $brandName . ' — ' . __('Gestão de frota, combustível e rotas de entrega');
    $pageDescription = __('Fuelly reúne diário de bordo, abastecimentos, trocas de óleo, relatórios de custo e a roteirização de entregas — da importação dos pedidos ao acompanhamento das rotas no mapa — em um só sistema.');
    $pageKeywords = __('fuelly, gestão de frota, controle de combustível, roteirização de entregas, otimização de rotas, acompanhamento de entregas, diário de bordo digital, troca de óleo, custo por km');
    $pageUrl = url()->current();
    $pageImage = asset('img/logo.png');

    /** Exemplo do produto no topo (dados de demonstração). */
    $exampleStops = [
        ['Mercado Ribeira', 'Centro', __('Entregue'), '#175641'],
        ['Padaria Pão Dourado', 'Vila Alay', __('Entregue'), '#175641'],
        ['Empório São Jorge', 'Vila Ribeirópolis', __('Não entregue'), '#B42318'],
        ['Hortifruti Estrela', 'Vila Tupi', __('A caminho'), '#7A5A00'],
        ['Açougue União', 'Vila São Francisco', __('Na rota'), '#0061FE'],
    ];

    /** Planos vêm da mesma configuração usada no sistema (config/plans.php). */
    $plans = app(PlanLimits::class)->publicPlans();
    $limitText = fn (?int $value, string $unlimited, string $limited): string => $value === null ? $unlimited : str_replace(':n', number_format($value, 0, ',', '.'), $limited);
    $comparison = [
        __('Veículos') => fn ($plan) => $limitText($plan['limits']['vehicles'], __('Sob consulta'), ':n'),
        __('Motoristas com acesso') => fn ($plan) => $limitText($plan['limits']['drivers'], __('Sob consulta'), ':n'),
        __('Diário de Bordo, relatórios e trocas de óleo') => fn ($plan) => __('Sim'),
        __('Rotas por mês') => fn ($plan) => $limitText($plan['limits']['routes_per_month'], __('Sem limite'), ':n'),
        __('Acompanhamento das rotas no mapa') => fn ($plan) => $plan['features']['deliveries'] ? __('Sim') : __('Não'),
        __('Busca de endereços por mês') => fn ($plan) => $limitText($plan['limits']['geocoding_per_month'], __('Sem limite'), ':n'),
        __('Exportar relatórios (PDF e Excel)') => fn ($plan) => $plan['features']['report_export'] ? __('Sim') : __('Não'),
    ];
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
    <header class="sticky top-0 z-40 border-b border-fleet-border bg-fleet-page">
        <div class="mx-auto flex min-h-[60px] max-w-[1200px] items-center justify-between gap-4 px-6 py-2">
            <a href="{{ url('/') }}" class="flex items-center" aria-label="{{ $brandName }}">
                <img src="{{ asset('img/logo.png') }}" alt="{{ $brandName }}" class="h-7 w-auto sm:h-8" />
            </a>
            @if (Route::has('login'))
                @include('partials.landing-nav')
            @endif
        </div>
    </header>

    <main>
        {{-- Topo --}}
        <section aria-labelledby="hero-title">
            <div class="mx-auto flex max-w-[1200px] flex-wrap items-center gap-14 px-6 pb-24 pt-20">
                <div class="min-w-0 flex-[1_1_440px]">
                    <h1 id="hero-title" class="max-w-[14ch] font-display text-[clamp(2.5rem,5vw,3.75rem)] font-medium leading-[1.08] tracking-[-0.02em] text-fleet-ink">
                        {{ __('Sua frota e suas entregas em um só lugar.') }}
                    </h1>
                    <p class="mt-6 max-w-[44ch] text-xl leading-relaxed text-fleet-secondary">
                        {{ __('Controle o combustível de cada veículo e monte as rotas do dia com as paradas já em ordem.') }}
                    </p>
                    <div class="mt-9 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('reports') }}" class="fleet-btn--primary fleet-btn--lg">{{ __('Acessar o sistema') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="fleet-btn--primary fleet-btn--lg">{{ __('Entrar na plataforma') }}</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="fleet-btn--outline fleet-btn--lg">{{ __('Cadastrar empresa') }}</a>
                            @endif
                        @endauth
                    </div>
                </div>

                <figure class="m-0 min-w-0 flex-[1_1_460px]">
                    <div class="overflow-hidden rounded-2xl border border-fleet-border bg-white shadow-fleet-card">
                        <div class="flex items-center justify-between gap-3 border-b border-fleet-surface px-5 py-4">
                            <div>
                                <p class="font-display text-lg font-medium">{{ __('Rota de hoje · Setor Centro') }}</p>
                                <p class="mt-1 text-sm text-fleet-secondary">{{ __('Marcos Lima · 7 paradas · 9,8 km') }}</p>
                            </div>
                            <span class="whitespace-nowrap rounded-pill bg-fleet-surface px-2.5 py-1 text-[13px] text-fleet-secondary">{{ __('Exemplo') }}</span>
                        </div>
                        <div class="px-5 pb-2 pt-4">
                            <div class="flex justify-between gap-3 text-sm text-fleet-secondary">
                                <span>{{ __('3 de 7 paradas visitadas') }}</span>
                                <span>{{ __('Próxima: Hortifruti Estrela') }}</span>
                            </div>
                            <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-fleet-surface" aria-hidden="true">
                                <div class="h-full w-[43%] rounded-full bg-fleet-primary"></div>
                            </div>
                        </div>
                        <ol class="m-0 list-none px-2.5 pb-3.5 pt-2">
                            @foreach ($exampleStops as $index => [$name, $district, $status, $color])
                                <li class="flex items-center gap-3.5 rounded-xl px-3 py-2.5">
                                    <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full text-[13px] font-semibold text-white" style="background-color: {{ $color }}">{{ $index + 1 }}</span>
                                    <span class="min-w-0 flex-auto">
                                        <span class="block text-[15px] font-medium">{{ $name }}</span>
                                        <span class="block text-[13px] text-fleet-muted">{{ $district }}</span>
                                    </span>
                                    <span class="flex-none text-sm font-medium" style="color: {{ $color }}">{{ $status }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </figure>
            </div>
        </section>

        {{-- Dois lados da operação --}}
        <section class="border-t border-fleet-border" aria-labelledby="two-title">
            <div class="mx-auto max-w-[1200px] px-6 py-24">
                <h2 id="two-title" class="max-w-[22ch] font-display text-[clamp(1.875rem,3.4vw,2.5rem)] font-normal leading-tight text-fleet-ink">
                    {{ __('Tudo o que a operação precisa, sem planilhas soltas.') }}
                </h2>
                <div class="mt-12 grid gap-6 md:grid-cols-2">
                    @foreach ([[__('Frota e combustível'), [__('Abastecimentos, pedágio e despesas de cada viagem no Diário de Bordo.'), __('Consumo médio e custo por km por veículo, com fechamento do mês.'), __('Trocas de óleo registradas, com aviso da próxima.')]], [__('Entregas e rotas'), [__('Pedidos do dia importados de uma planilha.'), __('Paradas ordenadas pelo menor percurso, por setor.'), __('Cada rota acompanhada no mapa: entregues, pendentes e próxima parada.')]]] as [$title, $items])
                        <div class="rounded-2xl bg-fleet-surface p-8">
                            <h3 class="font-display text-2xl font-medium text-fleet-ink">{{ $title }}</h3>
                            <ul class="mt-5 space-y-3.5 text-base leading-relaxed text-[#393633]">
                                @foreach ($items as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Como funciona --}}
        <section class="border-y border-fleet-border bg-white" aria-labelledby="steps-title">
            <div class="mx-auto max-w-[1200px] px-6 py-24">
                <h2 id="steps-title" class="font-display text-[clamp(1.875rem,3.4vw,2.5rem)] font-normal leading-tight text-fleet-ink">{{ __('Como funciona um dia de entregas') }}</h2>
                <ol class="mt-12 grid gap-8 md:grid-cols-3">
                    @foreach ([[__('Importe os pedidos'), __('Suba a planilha do dia no formato do modelo.')], [__('Monte a rota'), __('Escolha o setor, o motorista e o veículo. A ordem das paradas sai pronta.')], [__('Acompanhe no mapa'), __('Veja o andamento de cada motorista durante o dia.')]] as [$title, $text])
                        <li>
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-fleet-ink font-semibold text-fleet-on-dark">{{ $loop->iteration }}</span>
                            <h3 class="mt-5 font-display text-xl font-medium text-fleet-ink">{{ $title }}</h3>
                            <p class="mt-2 leading-relaxed text-fleet-secondary">{{ $text }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Planos --}}
        <section aria-labelledby="plans-title" id="planos">
            <div class="mx-auto max-w-[1200px] px-6 py-24">
                <h2 id="plans-title" class="font-display text-[clamp(1.875rem,3.4vw,2.5rem)] font-normal leading-tight text-fleet-ink">{{ __('Planos') }}</h2>
                <p class="mt-3 max-w-[56ch] text-lg leading-relaxed text-fleet-secondary">{{ __('O preço acompanha o tamanho da frota. Mude de plano quando ela crescer.') }}</p>

                <div class="mt-12 grid gap-6 lg:grid-cols-3">
                    @foreach ($plans as $plan)
                        @php($featured = $plan['key'] === 'profissional')
                        <article class="flex flex-col rounded-2xl bg-white p-8 shadow-fleet {{ $featured ? 'border-2 border-fleet-primary' : 'border border-fleet-border' }}">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-display text-2xl font-medium text-fleet-ink">{{ $plan['name'] }}</h3>
                                @if ($featured)
                                    <span class="rounded-pill bg-fleet-info px-3 py-1 text-[13px] font-medium text-fleet-primary-strong">{{ __('Mais completo') }}</span>
                                @endif
                            </div>
                            <p class="mt-2 text-[15px] leading-relaxed text-fleet-secondary">{{ $plan['description'] }}</p>
                            <p class="mt-6 font-display text-[2rem] font-medium tabular-nums text-fleet-ink">
                                {{ PlanLimits::formatPrice($plan['price_cents']) }}@if ($plan['price_cents'] !== null)<span class="font-sans text-[15px] font-normal text-fleet-secondary"> {{ __('/mês') }}</span>@endif
                            </p>
                            <ul class="mt-6 flex-grow space-y-3 text-[15px] leading-snug text-[#393633]">
                                <li>{{ $limitText($plan['limits']['vehicles'], __('Veículos e motoristas conforme a operação'), __(':n veículos e motoristas')) }}</li>
                                <li>{{ __('Diário de Bordo, relatórios e trocas de óleo') }}</li>
                                <li>{{ $limitText($plan['limits']['routes_per_month'], __('Rotas sem limite e acompanhamento no mapa'), __('Até :n rotas por mês, com acompanhamento no mapa')) }}</li>
                                <li>{{ $limitText($plan['limits']['geocoding_per_month'], __('Busca de endereços sem limite'), __('Busca de endereços incluída: :n por mês')) }}</li>
                                @if ($plan['features']['report_export'])
                                    <li>{{ __('Exportar relatórios em PDF e Excel') }}</li>
                                @endif
                            </ul>
                            <div class="mt-8">
                                @if ($plan['price_cents'] === null)
                                    <a href="{{ Route::has('login') ? route('login') : '#' }}" class="fleet-btn--outline fleet-btn--lg fleet-btn--block">{{ __('Falar com a gente') }}</a>
                                @elseif (Route::has('register'))
                                    <a href="{{ route('register') }}" class="{{ $featured ? 'fleet-btn--primary' : 'fleet-btn--outline' }} fleet-btn--lg fleet-btn--block">{{ __('Começar') }}</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-10 overflow-x-auto rounded-2xl border border-fleet-border bg-white">
                    <table class="w-full min-w-[640px] text-[15px]">
                        <caption class="px-6 py-5 text-left font-display text-xl font-medium text-fleet-ink">{{ __('O que cada plano inclui') }}</caption>
                        <thead>
                            <tr class="bg-fleet-surface text-left text-fleet-secondary">
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('Recurso') }}</th>
                                @foreach ($plans as $plan)
                                    <th scope="col" class="px-4 py-3 font-medium">{{ $plan['name'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($comparison as $label => $value)
                                <tr class="border-t border-fleet-surface">
                                    <th scope="row" class="px-6 py-3 text-left font-medium text-fleet-ink">{{ $label }}</th>
                                    @foreach ($plans as $plan)
                                        <td class="px-4 py-3 text-[#393633]">{{ $value($plan) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-sm text-fleet-muted">{{ __('Valores de exemplo, sujeitos a alteração.') }}</p>
            </div>
        </section>

        {{-- Faixa escura: o que já vem incluído --}}
        <section class="bg-fleet-dark text-fleet-on-dark" aria-labelledby="cost-title">
            <div class="mx-auto flex max-w-[1200px] flex-wrap gap-12 px-6 py-[88px]">
                <div class="min-w-0 flex-[1_1_440px]">
                    <h2 id="cost-title" class="max-w-[20ch] font-display text-[clamp(1.75rem,3vw,2.25rem)] font-normal leading-tight">{{ __('Mapa, rotas e endereços já incluídos.') }}</h2>
                    <p class="mt-4 max-w-[52ch] text-lg leading-relaxed text-[#B5B1AC]">{{ __('Sua empresa não precisa criar conta em serviço de mapas nem cadastrar chave. Tudo o que a roteirização usa já está no plano.') }}</p>
                </div>
                <ul class="m-0 flex min-w-0 flex-[1_1_420px] list-none flex-col gap-3 p-0">
                    @foreach ([[__('Mapa e ordem das paradas'), __('Mapa das entregas e cálculo do menor percurso.')], [__('Busca de endereços'), __('Endereços novos localizados automaticamente, dentro da cota do plano. Endereços já conhecidos não contam.')], [__('Ajuste manual quando precisar'), __('Corrija o endereço ou arraste o pino no mapa; depois da primeira entrega, vale a posição do motorista.')]] as [$title, $text])
                        <li class="rounded-xl bg-fleet-dark-elevated px-6 py-5">
                            <p class="font-medium">{{ $title }}</p>
                            <p class="mt-1.5 text-[15px] leading-relaxed text-[#B5B1AC]">{{ $text }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- Fechamento --}}
        <section aria-labelledby="soon-title">
            <div class="mx-auto flex max-w-[1200px] flex-wrap items-stretch gap-6 px-6 py-24">
                <div class="min-w-0 flex-[2_1_460px]">
                    <h2 id="soon-title" class="max-w-[18ch] font-display text-[clamp(1.875rem,3.4vw,2.5rem)] font-medium leading-tight text-fleet-ink">{{ __('Comece hoje a organizar frota e entregas.') }}</h2>
                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('reports') }}" class="fleet-btn--primary fleet-btn--lg">{{ __('Acessar o sistema') }}</a>
                        @else
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="fleet-btn--primary fleet-btn--lg">{{ __('Cadastrar empresa') }}</a>
                            @endif
                            <a href="{{ route('login') }}" class="fleet-btn--outline fleet-btn--lg">{{ __('Entrar') }}</a>
                        @endauth
                    </div>
                </div>
                <aside class="min-w-0 flex-[1_1_320px] rounded-2xl bg-fleet-surface p-7">
                    <span class="inline-flex rounded-pill bg-fleet-warning px-3 py-1 text-sm font-medium text-fleet-ink">{{ __('Em breve') }}</span>
                    <h3 class="mt-4 font-display text-xl font-medium text-fleet-ink">{{ __('App do motorista') }}</h3>
                    <p class="mt-2 leading-relaxed text-fleet-secondary">{{ __('As paradas no celular, a baixa de cada entrega e o envio da localização.') }}</p>
                </aside>
            </div>
        </section>
    </main>

    <footer class="bg-fleet-dark text-[#B5B1AC]">
        <div class="mx-auto flex max-w-[1200px] flex-wrap justify-between gap-3 px-6 py-10 text-sm">
            <p>&copy; {{ date('Y') }} {{ $brandName }}. {{ __('Todos os direitos reservados.') }}</p>
            @if (Route::has('login'))
                <a href="{{ route('login') }}" class="text-fleet-on-dark underline-offset-4 hover:underline">{{ __('Acesso à plataforma') }}</a>
            @endif
        </div>
    </footer>
@endsection
