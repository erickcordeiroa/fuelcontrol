<button {{ $attributes->merge(['type' => 'submit', 'class' => 'fleet-btn--primary fleet-btn--lg']) }}>
    {{ $slot }}
</button>
