@props(['payload', 'height' => 'h-[460px]'])

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
    <div x-ref="canvas" class="{{ $height }} w-full" role="region" aria-label="{{ __('Mapa das entregas') }}"></div>
</div>
