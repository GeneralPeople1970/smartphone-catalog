@csrf

@php
    $specsForEditing = $product->specsForEditing();
    $specsText = $specsForEditing === []
        ? ''
        : json_encode($specsForEditing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $selectedBrand = old('brand', $product->brand);
    $brandOptions = collect($brands ?? [])->pluck('name')->all();
@endphp

<div class="admin-form-grid">
    <div class="admin-field">
        <label for="brand">品牌</label>
        <select id="brand" name="brand" required class="admin-select">
            @if ($selectedBrand && ! in_array($selectedBrand, $brandOptions, true))
                <option value="{{ $selectedBrand }}" selected>{{ $selectedBrand }}</option>
            @endif
            @foreach ($brands as $brand)
                <option value="{{ $brand['name'] }}" @selected($selectedBrand === $brand['name'])>{{ $brand['displayName'] }} / {{ $brand['code'] }}</option>
            @endforeach
        </select>
        @error('brand')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field">
        <label for="name">手机名称</label>
        <input id="name" name="name" type="text" value="{{ old('name', $product->name) }}" required class="admin-input">
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field">
        <label for="slug">URL 标识</label>
        <input id="slug" name="slug" type="text" value="{{ old('slug', $product->slug) }}" placeholder="留空自动生成" class="admin-input">
        @error('slug')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field">
        <label for="status">状态</label>
        <select id="status" name="status" class="admin-select">
            <option value="draft" @selected(old('status', $product->status) === 'draft')>草稿</option>
            <option value="published" @selected(old('status', $product->status) === 'published')>已发布</option>
        </select>
        @error('status')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field">
        <label for="price">价格</label>
        <input id="price" name="price" type="text" value="{{ old('price', $product->price) }}" placeholder="例如 5999；空或 0 视为暂无价格" class="admin-input">
        @error('price')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field">
        <label for="soc_name">处理器</label>
        <input id="soc_name" name="soc_name" type="text" value="{{ old('soc_name', $product->soc_name) }}" class="admin-input">
        @error('soc_name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field">
        <label for="battery_capacity">电池容量 mAh</label>
        <input id="battery_capacity" name="battery_capacity" type="number" min="0" max="30000" value="{{ old('battery_capacity', $product->battery_capacity) }}" class="admin-input">
        @error('battery_capacity')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field">
        <label for="image_url">图片地址</label>
        <input id="image_url" name="image_url" type="text" value="{{ old('image_url', $product->image_url) }}" class="admin-input">
        @error('image_url')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="admin-field mt-6">
    <label for="specs_text">完整参数 JSON</label>
    <textarea id="specs_text" name="specs_text" placeholder='{"screenm":"6.7 英寸","feature":"卖点"}' class="admin-textarea">{{ old('specs_text', $specsText) }}</textarea>
    @error('specs_text')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="mt-6 flex flex-wrap items-center gap-3">
    <button type="submit" class="admin-button-primary">保存手机</button>
    <a href="{{ route('products.index') }}" class="admin-button">返回列表</a>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const specsTextarea = document.getElementById('specs_text');

            if (! specsTextarea) {
                return;
            }

            const mappings = {
                brand: 'company',
                name: 'phonename',
                image_url: 'imgurl',
                price: 'price',
                soc_name: 'socname',
                battery_capacity: 'battery',
            };

            const numericFields = new Set(['price', 'battery_capacity']);

            const parseSpecs = () => {
                const value = specsTextarea.value.trim();

                if (value === '') {
                    return {};
                }

                try {
                    const parsed = JSON.parse(value);

                    return parsed && typeof parsed === 'object' && ! Array.isArray(parsed) ? parsed : null;
                } catch (error) {
                    return null;
                }
            };

            const normalizedValue = (input) => {
                const value = input.value.trim();

                if (! numericFields.has(input.id)) {
                    return value;
                }

                if (value === '') {
                    return 0;
                }

                return /^-?\d+(\.\d+)?$/.test(value) ? Number(value) : value;
            };

            const syncSpecsText = () => {
                const specs = parseSpecs();

                if (specs === null) {
                    return;
                }

                Object.entries(mappings).forEach(([inputId, specKey]) => {
                    const input = document.getElementById(inputId);

                    if (input) {
                        specs[specKey] = normalizedValue(input);
                    }
                });

                specsTextarea.value = JSON.stringify(specs, null, 2);
            };

            Object.keys(mappings).forEach((inputId) => {
                const input = document.getElementById(inputId);

                if (input) {
                    input.addEventListener('input', syncSpecsText);
                    input.addEventListener('change', syncSpecsText);
                }
            });
        });
    </script>
@endonce
