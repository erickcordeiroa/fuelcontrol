<div class="sticky top-0 z-10 border-b border-fleet-border bg-fleet-card/90 backdrop-blur">
    <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-8">
        <div class="relative max-w-xl flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-fleet-muted">⌕</span>
            <input
                type="search"
                class="w-full rounded-xl border border-fleet-border bg-fleet-page py-2.5 pl-10 pr-3 text-sm text-fleet-ink placeholder:text-fleet-muted focus:border-fleet-primary focus:outline-none focus:ring-2 focus:ring-fleet-primary/20"
                placeholder="{{ __('Pesquisar Frota…') }}"
                disabled
            />
        </div>
        <div class="flex flex-wrap items-center justify-end gap-3 text-sm font-medium text-fleet-secondary">
            <span class="hidden md:inline">{{ __('Frota') }}</span>
            <span class="hidden md:inline">{{ __('Motoristas') }}</span>
            <span class="hidden md:inline">{{ __('Manutenção') }}</span>
            @if (auth()->user()->isAdmin())
                <a
                    href="{{ route('assets.vehicles') }}"
                    wire:navigate
                    class="rounded-xl bg-fleet-dark px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:opacity-90"
                >
                    + {{ __('Veículo') }}
                </a>
            @endif
            <livewire:layout.user-menu />
        </div>
    </div>

    <div class="flex gap-2 overflow-x-auto border-t border-fleet-border px-4 py-2 text-sm lg:hidden">
        <a href="{{ route('dashboard') }}" wire:navigate class="whitespace-nowrap rounded-lg bg-fleet-page px-3 py-1">{{ __('Painel') }}</a>
        <a href="{{ route('logbook') }}" wire:navigate class="whitespace-nowrap rounded-lg bg-fleet-page px-3 py-1">{{ __('Diário') }}</a>
        <a href="{{ route('reports') }}" wire:navigate class="whitespace-nowrap rounded-lg bg-fleet-page px-3 py-1">{{ __('Relatórios') }}</a>
        <a href="{{ route('profile') }}" wire:navigate class="whitespace-nowrap rounded-lg bg-fleet-page px-3 py-1">{{ __('Perfil') }}</a>
    </div>
</div>
