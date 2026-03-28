@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();

    $navIcon = function (bool $active): string {
        return $active
            ? 'inline-flex h-8 w-8 items-center justify-center rounded-lg bg-fleet-primary text-white shadow-sm'
            : 'inline-flex h-8 w-8 items-center justify-center rounded-lg bg-fleet-primary/10 text-fleet-primary';
    };
@endphp

<aside class="hidden w-64 shrink-0 flex-col border-r border-fleet-border bg-fleet-sidebar lg:flex">
    <div class="px-6 py-8">
        <p class="text-fleet-label text-fleet-muted">{{ __('Fleet Command') }}</p>
        @if ($isAdmin && filled($user->company_name))
            <p class="mt-2 text-sm font-semibold text-fleet-ink">{{ $user->company_name }}</p>
            <p class="mt-0.5 text-fleet-body text-fleet-secondary">{{ __('Administrador') }}</p>
        @else
            <p class="mt-2 text-sm font-semibold text-fleet-ink">{{ $isAdmin ? __('Função Administrador') : __('Motorista') }}</p>
        @endif
    </div>

    <nav class="flex-1 space-y-1 px-3">
        <a
            href="{{ route('dashboard') }}"
            wire:navigate
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('dashboard') ? 'bg-white text-fleet-ink shadow-fleet' : 'text-fleet-secondary hover:bg-white/80' }}"
        >
            <span class="{{ $navIcon(request()->routeIs('dashboard')) }}" aria-hidden="true">
                <x-icons.home class="h-4 w-4" />
            </span>
            {{ __('Painel') }}
        </a>

        @if ($isAdmin)
            <a
                href="{{ route('vehicles.index') }}"
                wire:navigate
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                    {{ request()->routeIs('vehicles.*') ? 'bg-white text-fleet-ink shadow-fleet' : 'text-fleet-secondary hover:bg-white/80' }}"
            >
                <span class="{{ $navIcon(request()->routeIs('vehicles.*')) }}" aria-hidden="true">
                    <x-icons.truck class="h-4 w-4" />
                </span>
                {{ __('Veículos') }}
            </a>
            <a
                href="{{ route('drivers.index') }}"
                wire:navigate
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                    {{ request()->routeIs('drivers.*') ? 'bg-white text-fleet-ink shadow-fleet' : 'text-fleet-secondary hover:bg-white/80' }}"
            >
                <span class="{{ $navIcon(request()->routeIs('drivers.*')) }}" aria-hidden="true">
                    <x-icons.users class="h-4 w-4" />
                </span>
                {{ __('Motoristas') }}
            </a>
            <a
                href="{{ route('gas-stations.index') }}"
                wire:navigate
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                    {{ request()->routeIs('gas-stations.*') ? 'bg-white text-fleet-ink shadow-fleet' : 'text-fleet-secondary hover:bg-white/80' }}"
            >
                <span class="{{ $navIcon(request()->routeIs('gas-stations.*')) }}" aria-hidden="true">
                    <x-icons.building-storefront class="h-4 w-4" />
                </span>
                {{ __('Postos') }}
            </a>
        @endif

        <a
            href="{{ route('logbook') }}"
            wire:navigate
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('logbook') ? 'bg-white text-fleet-ink shadow-fleet' : 'text-fleet-secondary hover:bg-white/80' }}"
        >
            <span class="{{ $navIcon(request()->routeIs('logbook')) }}" aria-hidden="true">
                <x-icons.clipboard-document class="h-4 w-4" />
            </span>
            {{ __('Diário de Bordo') }}
        </a>

        <a
            href="{{ route('reports') }}"
            wire:navigate
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('reports') ? 'bg-white text-fleet-ink shadow-fleet' : 'text-fleet-secondary hover:bg-white/80' }}"
        >
            <span class="{{ $navIcon(request()->routeIs('reports')) }}" aria-hidden="true">
                <x-icons.chart-bar class="h-4 w-4" />
            </span>
            {{ __('Relatórios') }}
        </a>

        <a
            href="{{ route('profile') }}"
            wire:navigate
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('profile') ? 'bg-white text-fleet-ink shadow-fleet' : 'text-fleet-secondary hover:bg-white/80' }}"
        >
            <span class="{{ $navIcon(request()->routeIs('profile')) }}" aria-hidden="true">
                <x-icons.user-circle class="h-4 w-4" />
            </span>
            {{ __('Perfil') }}
        </a>
    </nav>

    <div class="mt-auto space-y-1 border-t border-fleet-border px-3 py-4">
        <div class="rounded-xl px-3 py-2 text-fleet-body text-fleet-muted">{{ __('Configurações') }}</div>
        <div class="rounded-xl px-3 py-2 text-fleet-body text-fleet-muted">{{ __('Suporte') }}</div>
    </div>
</aside>
