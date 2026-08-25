@props(['value'])

<label {{ $attributes->merge(['class' => 'admin-label']) }}>
    {{ $value ?? $slot }}
</label>
