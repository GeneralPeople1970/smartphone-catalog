<button {{ $attributes->merge(['type' => 'button', 'class' => 'admin-button disabled:opacity-25']) }}>
    {{ $slot }}
</button>
