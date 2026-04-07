@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $quickSearchPages = [
        [
            'label' => __('Painel'),
            'description' => __('Indicadores operacionais'),
            'url' => route('dashboard'),
        ],
        [
            'label' => __('Diário de Bordo'),
            'description' => __('Registros e lançamentos da frota'),
            'url' => route('logbook'),
        ],
        [
            'label' => __('Relatórios'),
            'description' => __('Análises de rotas e custos'),
            'url' => route('reports'),
        ],
        [
            'label' => __('Perfil'),
            'description' => __('Dados da conta e senha'),
            'url' => route('profile'),
        ],
    ];

    if ($isAdmin) {
        array_splice($quickSearchPages, 1, 0, [
            [
                'label' => __('Veículos'),
                'description' => __('Cadastro e gestão da frota'),
                'url' => route('vehicles.index'),
            ],
            [
                'label' => __('Motoristas'),
                'description' => __('Cadastro e gestão de motoristas'),
                'url' => route('drivers.index'),
            ],
            [
                'label' => __('Postos'),
                'description' => __('Postos e preços de combustível'),
                'url' => route('gas-stations.index'),
            ],
        ]);
    }
@endphp

<div class="sticky top-0 z-10 border-b border-fleet-border bg-fleet-card/90 backdrop-blur">
    <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-8">
        <div
            class="relative max-w-xl flex-1"
            x-data="fleetPageSearch(@js($quickSearchPages))"
            @keydown.escape.window="onEscape()"
        >
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-fleet-secondary" aria-hidden="true">
                <x-icons.magnifying-glass class="h-5 w-5" />
            </span>
            <input
                type="search"
                class="fleet-search-field"
                placeholder="{{ __('Pesquisar…') }}"
                x-model="query"
                @focus="onFocus()"
                @input="onInput()"
                @keydown.down.prevent="selectNext()"
                @keydown.up.prevent="selectPrevious()"
                @keydown.enter.prevent="goToActive()"
                @click.outside="open = false"
                autocomplete="off"
                aria-label="{{ __('Pesquisar páginas') }}"
            />

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-1"
                class="absolute left-0 right-0 top-full z-30 mt-2 overflow-hidden rounded-2xl border border-fleet-border bg-white shadow-fleet"
                style="display: none;"
            >
                <template x-for="(page, index) in filteredPages" :key="page.url">
                    <a
                        :href="page.url"
                        wire:navigate
                        @mouseenter="selectIndex(index)"
                        @click.prevent="goToPage(page)"
                        class="block border-b border-fleet-border/70 px-4 py-3 last:border-b-0"
                        :class="index === activeIndex ? 'bg-fleet-primary/10 text-fleet-ink' : 'text-fleet-secondary hover:bg-fleet-page'"
                    >
                        <span class="block text-sm font-semibold" x-text="page.label"></span>
                        <span class="mt-0.5 block text-xs text-fleet-muted" x-text="page.description"></span>
                    </a>
                </template>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-3 text-sm font-medium text-fleet-secondary">
            <livewire:layout.user-menu />
        </div>
    </div>

    <div class="flex gap-2 overflow-x-auto border-t border-fleet-border px-4 py-2 text-sm lg:hidden">
        <a href="{{ route('dashboard') }}" wire:navigate class="whitespace-nowrap rounded-lg bg-fleet-primary/10 px-3 py-1.5 font-medium text-fleet-primary">{{ __('Painel') }}</a>
        <a href="{{ route('logbook') }}" wire:navigate class="whitespace-nowrap rounded-lg bg-fleet-primary/10 px-3 py-1.5 font-medium text-fleet-primary">{{ __('Diário') }}</a>
        <a href="{{ route('reports') }}" wire:navigate class="whitespace-nowrap rounded-lg bg-fleet-primary/10 px-3 py-1.5 font-medium text-fleet-primary">{{ __('Relatórios') }}</a>
        <a href="{{ route('profile') }}" wire:navigate class="whitespace-nowrap rounded-lg bg-fleet-primary/10 px-3 py-1.5 font-medium text-fleet-primary">{{ __('Perfil') }}</a>
    </div>
</div>
