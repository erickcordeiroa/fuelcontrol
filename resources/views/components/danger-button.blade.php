<button {{ $attributes->merge(['type' => 'submit', 'class' => 'fleet-btn--danger fleet-btn--lg']) }}>
    {{ $slot }}
</button>
