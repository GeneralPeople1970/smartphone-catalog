<button {{ $attributes->merge(['type' => 'submit', 'class' => 'admin-button-primary']) }}>
    {{ $slot }}
</button>
