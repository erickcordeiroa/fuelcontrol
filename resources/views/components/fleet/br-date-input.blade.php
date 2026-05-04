@props([
    'for' => 'date',
    /** @var bool Quando true, envia Y-m-d ao Livewire ao completar 8 dígitos válidos; quando false, idem + útil com wire:model sem .live */
    'live' => true,
])

<input
    {{ $attributes->merge(['class' => 'fleet-field']) }}
    type="text"
    inputmode="numeric"
    autocomplete="off"
    placeholder="dd/mm/aaaa"
    maxlength="10"
    x-data="fleetBrDateField(@js($for), { live: @js($live) })"
    x-bind:value="format()"
    x-on:keydown="onKeydown($event)"
    x-on:beforeinput="onBeforeInput($event)"
    x-on:paste="onPaste($event)"
    x-on:blur="onBlur($event)"
/>
