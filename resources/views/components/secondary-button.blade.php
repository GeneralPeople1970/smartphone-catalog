<button {{ $attributes->merge(['type' => 'button', 'class' => 'admin-button']) }}>
    {{ $slot }}
</button>
