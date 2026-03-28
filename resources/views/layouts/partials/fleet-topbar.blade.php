<div class="sticky top-0 z-10 border-b border-fleet-border bg-fleet-card/90 backdrop-blur">
    <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-8">
        <div class="relative max-w-xl flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-fleet-secondary" aria-hidden="true">
                <x-icons.magnifying-glass class="h-5 w-5" />
            </span>
            <input
                type="search"
                class="fleet-search-field"
                placeholder="{{ __('Pesquisar…') }}"
                disabled
            />
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
