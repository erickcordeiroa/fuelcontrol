<div class="relative" x-data="{ open: false }">
    <button
        type="button"
        @click="open = !open"
        class="flex min-h-[2.5rem] items-center gap-2 rounded-pill border border-fleet-border bg-white px-4 py-2 text-sm font-medium text-fleet-ink transition-colors duration-150 hover:border-fleet-hairline-strong"
    >
        <span>{{ auth()->user()->name }}</span>
        <svg class="h-4 w-4 text-fleet-muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-transition
        class="absolute right-0 z-20 mt-2 w-48 rounded-xl border border-fleet-border bg-white py-1 text-sm shadow-fleet-card"
        style="display: none;"
    >
        <a href="{{ route('profile') }}" wire:navigate class="block px-4 py-2 text-fleet-ink hover:bg-fleet-page">{{ __('Perfil') }}</a>
        <button type="button" wire:click="logout" class="block w-full px-4 py-2 text-left text-fleet-ink hover:bg-fleet-page">
            {{ __('Sair') }}
        </button>
    </div>
</div>
