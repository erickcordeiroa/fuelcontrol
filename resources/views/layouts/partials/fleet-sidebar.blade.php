@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $menu = \App\Support\FleetMenu::for($user);

    $navIcon = function (bool $active): string {
        return $active
            ? 'inline-flex h-8 w-8 items-center justify-center rounded-lg bg-fleet-primary text-white shadow-sm'
            : 'inline-flex h-8 w-8 items-center justify-center rounded-lg bg-fleet-primary/10 text-fleet-primary';
    };
@endphp

<aside class="hidden w-64 shrink-0 flex-col overflow-y-auto border-r border-fleet-border bg-fleet-sidebar lg:flex">
    <div class="px-6 py-8">
        @if ($isAdmin && filled($user->company_name))
            <p class="mt-2 text-sm font-semibold text-fleet-ink">{{ $user->company_name }}</p>
            <p class="mt-0.5 text-fleet-body text-fleet-secondary">{{ __('Administrador') }}</p>
        @else
            <p class="mt-2 text-sm font-semibold text-fleet-ink">{{ $isAdmin ? __('Função Administrador') : __('Motorista') }}</p>
        @endif
    </div>

    <nav class="flex-1 space-y-6 px-3 pb-6" aria-label="{{ __('Menu principal') }}">
        @foreach ($menu as $section)
            <div>
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-fleet-muted">{{ $section['label'] }}</p>
                <div class="space-y-1">
                    @foreach ($section['items'] as $item)
                        @php($isActive = request()->routeIs($item['active']))
                        <a
                            href="{{ route($item['route']) }}"
                            wire:navigate
                            @if ($isActive) aria-current="page" @endif
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                                {{ $isActive ? 'bg-white text-fleet-ink shadow-fleet' : 'text-fleet-secondary hover:bg-white/80' }}"
                        >
                            <span class="{{ $navIcon($isActive) }}" aria-hidden="true">
                                <x-dynamic-component :component="'icons.'.$item['icon']" class="h-4 w-4" />
                            </span>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

</aside>
