@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'admin-alert-success']) }}>
        {{ $status }}
    </div>
@endif
