@props(['name', 'checked' => false])
<label class="admin-checkbox-field">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" class="admin-checkbox" @checked($checked)>
    {{ $slot }}
</label>
