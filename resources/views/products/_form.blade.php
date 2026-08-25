@csrf

@php
    $specsForEditing = $product->specsForEditing();
    $specsText = $specsForEditing === []
        ? ''
        : json_encode($specsForEditing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $selectedBrand = old('brand', $product->brand);
    $brandOptions = collect($brands ?? [])->pluck('name')->all();
@endphp

{{-- Field widths come from `.admin-form-grid` / `.admin-field-*`: short values
     keep short boxes and only the long ones span two columns. --}}
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
            <p class="admin-field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field">
        <label for="name">手机名称</label>
        <input id="name" name="name" type="text" value="{{ old('name', $product->name) }}" required class="admin-input">
        @error('name')
            <p class="admin-field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field">
        <label for="slug">URL 标识</label>
        <input id="slug" name="slug" type="text" value="{{ old('slug', $product->slug) }}" class="admin-input">
        <p class="admin-hint">留空时按品牌和型号自动生成。</p>
        @error('slug')
            <p class="admin-field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field admin-field-narrow">
        <label for="status">状态</label>
        <select id="status" name="status" class="admin-select">
            <option value="draft" @selected(old('status', $product->status) === 'draft')>草稿</option>
            <option value="published" @selected(old('status', $product->status) === 'published')>已发布</option>
        </select>
        @error('status')
            <p class="admin-field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field admin-field-narrow">
        <label for="price">价格</label>
        <input id="price" name="price" type="text" value="{{ old('price', $product->price) }}" class="admin-input">
        <p class="admin-hint">例如 5999；留空或 0 视为暂无价格。</p>
        @error('price')
            <p class="admin-field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field">
        <label for="soc_name">处理器</label>
        <input id="soc_name" name="soc_name" type="text" value="{{ old('soc_name', $product->soc_name) }}" class="admin-input">
        @error('soc_name')
            <p class="admin-field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field admin-field-narrow">
        <label for="battery_capacity">电池容量 mAh</label>
        <input id="battery_capacity" name="battery_capacity" type="number" min="0" max="30000" value="{{ old('battery_capacity', $product->battery_capacity) }}" class="admin-input">
        @error('battery_capacity')
            <p class="admin-field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="admin-field admin-field-wide">
        <label for="image_url">图片地址</label>
        <input id="image_url" name="image_url" type="text" value="{{ old('image_url', $product->image_url) }}" class="admin-input">
        <p class="admin-hint">支持站内相对路径或 https 图片地址。</p>
        @error('image_url')
            <p class="admin-field-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="admin-field mt-6">
    <label for="specs_text">完整参数 JSON</label>
    <textarea id="specs_text" name="specs_text" placeholder='{"screenm":"6.7 英寸","feature":"卖点"}' class="admin-textarea">{{ old('specs_text', $specsText) }}</textarea>
    <p class="admin-hint">上面的字段会自动同步到这里对应的键，其余参数可直接编辑。</p>
    @error('specs_text')
        <p class="admin-field-error">{{ $message }}</p>
    @enderror
</div>

<div class="admin-form-actions mt-6">
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
