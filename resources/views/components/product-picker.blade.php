@props(['selected' => null])
<div class="admin-field" data-product-picker>
    <label for="product_search">手机型号</label>
    <input id="product_search" type="search" autocomplete="off" maxlength="191" class="admin-input" placeholder="型号、品牌、处理器或 ID">
    <select id="product_id" name="product_id" required class="admin-select mt-2" aria-label="选择手机">
        <option value="">选择手机</option>
        @if ($selected)
            <option value="{{ $selected->id }}" selected>#{{ $selected->id }} {{ $selected->brand }} - {{ $selected->name }}</option>
        @endif
    </select>
    <span class="admin-hint" data-picker-status role="status"></span>
    <button type="button" class="admin-button" data-picker-retry hidden>重试</button>
</div>
