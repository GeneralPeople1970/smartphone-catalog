<button {{ $attributes->merge(['type' => 'submit', 'class' => 'admin-button-danger']) }}>
    {{ $slot }}
</button>
