@props(['value'])

<label {{ $attributes->merge(['class' => 'fleet-label']) }}>
    {{ $value ?? $slot }}
</label>
