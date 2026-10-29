@props(['payload', 'height' => 'h-[460px]', 'fullscreen' => false])

<div
    wire:ignore
    x-data="fleetRouteMap(@js($payload), @js([
        'url' => config('services.map_tiles.url'),
        'attribution' => config('services.map_tiles.attribution'),
        'maxZoom' => config('services.map_tiles.max_zoom'),
    ]))"
    x-on:route-map-update.window="update($event.detail.payload)"
    {{ $attributes->merge(['class' => 'relative isolate overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card']) }}
>
    <div x-ref="canvas" class="{{ $height }} w-full" x-bind:class="isFullscreen && '!h-screen'" role="region" aria-label="{{ __('Mapa das entregas') }}"></div>
    @if ($fullscreen)
        <button
            type="button"
            x-on:click="toggleFullscreen()"
            class="absolute right-3 top-3 z-[1000] rounded-full border border-fleet-border bg-fleet-card px-3 py-1.5 text-xs font-semibold text-fleet-ink shadow-fleet hover:bg-fleet-page"
            x-text="isFullscreen ? '{{ __('Sair da tela cheia') }}' : '{{ __('Tela cheia') }}'"
        ></button>
    @endif
</div>
