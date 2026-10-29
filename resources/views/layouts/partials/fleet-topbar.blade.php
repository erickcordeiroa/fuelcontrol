@php
    $user = auth()->user();
    $quickSearchPages = \App\Support\FleetMenu::searchablePages($user);
    $mobileMenu = \App\Support\FleetMenu::for($user);
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
                placeholder="{{ __('Buscar página ou categoria…') }}"
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
                <div class="max-h-[70vh] overflow-y-auto">
                    <template x-for="(page, index) in filteredPages" :key="page.url">
                        <div>
                            <p
                                x-show="startsCategory(index)"
                                class="border-b border-fleet-border/70 bg-fleet-page px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-fleet-muted"
                                x-text="page.category"
                            ></p>
                            <a
                                :href="page.url"
                                wire:navigate
                                @mouseenter="selectIndex(index)"
                                @click.prevent="goToPage(page)"
                                class="block border-b border-fleet-border/70 px-4 py-3"
                                :class="index === activeIndex ? 'bg-fleet-primary/10 text-fleet-ink' : 'text-fleet-secondary hover:bg-fleet-page'"
                            >
                                <span class="block text-sm font-semibold" x-text="page.label"></span>
                                <span class="mt-0.5 block text-xs text-fleet-muted" x-text="page.description"></span>
                            </a>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-3 text-sm font-medium text-fleet-secondary">
            <livewire:layout.user-menu />
        </div>
    </div>

    <nav class="flex items-center gap-2 overflow-x-auto border-t border-fleet-border px-4 py-2 text-sm lg:hidden" aria-label="{{ __('Menu principal') }}">
        @foreach ($mobileMenu as $section)
            @unless ($loop->first)
                <span class="h-5 w-px shrink-0 bg-fleet-border" aria-hidden="true"></span>
            @endunless
            @foreach ($section['items'] as $item)
                @php($isActive = request()->routeIs($item['active']))
                <a
                    href="{{ route($item['route']) }}"
                    wire:navigate
                    @if ($isActive) aria-current="page" @endif
                    class="whitespace-nowrap rounded-lg px-3 py-1.5 font-medium {{ $isActive ? 'bg-fleet-primary text-white' : 'bg-fleet-primary/10 text-fleet-primary' }}"
                >{{ $item['label'] }}</a>
            @endforeach
        @endforeach
    </nav>
</div>
