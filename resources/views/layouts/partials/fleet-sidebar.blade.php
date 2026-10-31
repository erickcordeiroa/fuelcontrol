@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $menu = \App\Support\FleetMenu::for($user);

@endphp

<aside class="hidden w-64 shrink-0 flex-col overflow-y-auto border-r border-fleet-border bg-fleet-sidebar lg:sticky lg:top-0 lg:flex lg:h-[100dvh]">
    <a href="{{ route('reports') }}" wire:navigate class="mx-5 mt-6 flex items-center" aria-label="{{ __('Kilomo — página inicial') }}">
        <x-fleet.wordmark class="text-2xl" />
    </a>

    <div class="mx-5 mt-6 border-t border-fleet-border pt-4">
        @if ($isAdmin && filled($user->company_name))
            <p class="text-sm font-medium leading-snug text-fleet-ink">{{ $user->company_name }}</p>
            <p class="mt-0.5 text-sm text-fleet-secondary">{{ __('Administrador') }}</p>
        @else
            <p class="text-sm font-medium leading-snug text-fleet-ink">{{ $user->name }}</p>
            <p class="mt-0.5 text-sm text-fleet-secondary">{{ $isAdmin ? __('Administrador') : __('Motorista') }}</p>
        @endif
    </div>

    <nav class="mt-6 flex-1 space-y-6 px-3 pb-6" aria-label="{{ __('Menu principal') }}">
        @foreach ($menu as $section)
            <div>
                <p class="px-3 pb-1.5 text-sm font-medium text-fleet-secondary">{{ $section['label'] }}</p>
                <div class="space-y-0.5">
                    @foreach ($section['items'] as $item)
                        @php($isActive = request()->routeIs($item['active']))
                        <a
                            href="{{ route($item['route']) }}"
                            wire:navigate
                            @if ($isActive) aria-current="page" @endif
                            class="flex items-center gap-3 rounded-pill px-3.5 py-2 text-base transition-colors duration-150
                                {{ $isActive ? 'bg-fleet-ink font-medium text-fleet-on-dark' : 'text-fleet-ink hover:bg-[#e4ddd3]' }}"
                        >
                            <x-dynamic-component :component="'icons.'.$item['icon']" class="h-[18px] w-[18px] shrink-0 {{ $isActive ? 'text-fleet-on-dark' : 'text-fleet-secondary' }}" />
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</aside>
