@props(['color', 'label'])

<span
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-[11px] font-semibold']) }}
    style="background-color: {{ $color }}1f; color: {{ $color }};"
>
    <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $color }};"></span>
    {{ $label }}
</span>
