@props([
    'trip',
])

@php
    $canUpdate = auth()->user()->can('update', $trip);
    $canViewHistory = auth()->user()->can('view', $trip);
@endphp

@if ($canUpdate || $canViewHistory)
    <div class="flex justify-end">
        <div class="relative z-20 inline-flex" x-data="tripRowActionsDropdown" @keydown.escape.window="open = false">
            <button
                type="button"
                x-ref="trigger"
                @click.stop="toggle()"
                x-bind:aria-expanded="open"
                aria-haspopup="menu"
                class="relative z-20 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-fleet-border bg-fleet-card text-fleet-secondary shadow-sm transition hover:border-fleet-primary/30 hover:bg-fleet-page hover:text-fleet-ink focus:outline-none focus:ring-2 focus:ring-fleet-primary/25"
                title="{{ __('Menu de ações') }}"
            >
                <span class="sr-only">{{ __('Abrir menu de ações') }}</span>
                <x-icons.ellipsis-vertical class="h-5 w-5" />
            </button>

            <template x-teleport="body">
                <div
                    x-show="open"
                    x-transition
                    @click.outside="closeIfOutside($event)"
                    role="menu"
                    class="fixed z-[200] w-48 rounded-xl border border-fleet-border bg-fleet-card py-1 text-left text-sm shadow-lg"
                    x-bind:style="'top:' + top + 'px;left:' + left + 'px'"
                    style="display: none;"
                >
                    @if ($canUpdate)
                        <a
                            href="{{ route('logbook', ['trip' => $trip]) }}"
                            wire:navigate
                            role="menuitem"
                            @click="open = false"
                            class="block px-3 py-2.5 text-fleet-ink hover:bg-fleet-page"
                        >
                            {{ __('Editar registro') }}
                        </a>
                    @endif
                    @if ($canViewHistory)
                        <button
                            type="button"
                            role="menuitem"
                            wire:click="openTripHistory({{ $trip->id }})"
                            @click="open = false"
                            class="w-full px-3 py-2.5 text-left text-fleet-ink hover:bg-fleet-page"
                        >
                            {{ __('Histórico de alterações') }}
                        </button>
                    @endif
                </div>
            </template>
        </div>
    </div>
@else
    <span class="text-fleet-muted">—</span>
@endif
