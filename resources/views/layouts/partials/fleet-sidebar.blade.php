@php
    $isAdmin = auth()->user()->isAdmin();
@endphp

<aside class="hidden w-64 shrink-0 flex-col border-r border-fleet-border bg-fleet-sidebar lg:flex">
    <div class="px-6 py-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-fleet-muted">Fleet Command</p>
        <p class="mt-1 text-sm font-semibold text-fleet-ink">{{ $isAdmin ? __('Função Administrador') : __('Motorista') }}</p>
    </div>

    <nav class="flex-1 space-y-1 px-3">
        <a
            href="{{ route('dashboard') }}"
            wire:navigate
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('dashboard') ? 'bg-white text-fleet-ink shadow-sm' : 'text-fleet-secondary hover:bg-white/70' }}"
        >
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-fleet-page text-fleet-ink">⌂</span>
            {{ __('Painel') }}
        </a>

        @if ($isAdmin)
            <a
                href="{{ route('assets.vehicles') }}"
                wire:navigate
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                    {{ request()->routeIs('assets.vehicles') || request()->routeIs('assets.drivers') ? 'bg-white text-fleet-ink shadow-sm' : 'text-fleet-secondary hover:bg-white/70' }}"
            >
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-fleet-page text-fleet-ink">◆</span>
                {{ __('Ativos') }}
            </a>
        @endif

        <a
            href="{{ route('logbook') }}"
            wire:navigate
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('logbook') ? 'bg-white text-fleet-ink shadow-sm' : 'text-fleet-secondary hover:bg-white/70' }}"
        >
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-fleet-page text-fleet-ink">▣</span>
            {{ __('Diário de Bordo') }}
        </a>

        <a
            href="{{ route('reports') }}"
            wire:navigate
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('reports') ? 'bg-white text-fleet-ink shadow-sm' : 'text-fleet-secondary hover:bg-white/70' }}"
        >
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-fleet-page text-fleet-ink">▤</span>
            {{ __('Relatórios') }}
        </a>

        <a
            href="{{ route('profile') }}"
            wire:navigate
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('profile') ? 'bg-white text-fleet-ink shadow-sm' : 'text-fleet-secondary hover:bg-white/70' }}"
        >
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-fleet-page text-fleet-ink">◎</span>
            {{ __('Perfil') }}
        </a>
    </nav>

    <div class="mt-auto space-y-1 border-t border-fleet-border px-3 py-4">
        <div class="rounded-xl px-3 py-2 text-sm text-fleet-muted">{{ __('Configurações') }}</div>
        <div class="rounded-xl px-3 py-2 text-sm text-fleet-muted">{{ __('Suporte') }}</div>
    </div>
</aside>
