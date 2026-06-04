@csrf

@php
    $specsForEditing = $product->specsForEditing();
    $specsText = $specsForEditing === []
        ? ''
        : json_encode($specsForEditing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <div>
        <label for="brand" class="block text-sm font-medium text-gray-700">品牌</label>
        <input id="brand" name="brand" type="text" value="{{ old('brand', $product->brand) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
        @error('brand')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="name" class="block text-sm font-medium text-gray-700">手机名称</label>
        <input id="name" name="name" type="text" value="{{ old('name', $product->name) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="slug" class="block text-sm font-medium text-gray-700">URL 标识</label>
        <input id="slug" name="slug" type="text" value="{{ old('slug', $product->slug) }}" placeholder="可选" class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
        @error('slug')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="status" class="block text-sm font-medium text-gray-700">状态</label>
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
            <option value="draft" @selected(old('status', $product->status) === 'draft')>草稿</option>
            <option value="published" @selected(old('status', $product->status) === 'published')>已发布</option>
        </select>
        @error('status')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="price" class="block text-sm font-medium text-gray-700">价格</label>
        <input id="price" name="price" type="text" value="{{ old('price', $product->price) }}" placeholder="例如 5999 或 暂无" class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
        @error('price')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="soc_name" class="block text-sm font-medium text-gray-700">处理器</label>
        <input id="soc_name" name="soc_name" type="text" value="{{ old('soc_name', $product->soc_name) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
        @error('soc_name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="battery_capacity" class="block text-sm font-medium text-gray-700">电池容量 mAh</label>
        <input id="battery_capacity" name="battery_capacity" type="number" min="0" max="30000" value="{{ old('battery_capacity', $product->battery_capacity) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
        @error('battery_capacity')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="image_url" class="block text-sm font-medium text-gray-700">图片地址</label>
        <input id="image_url" name="image_url" type="text" value="{{ old('image_url', $product->image_url) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
        @error('image_url')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-6">
    <label for="specs_text" class="block text-sm font-medium text-gray-700">完整参数 JSON</label>
    <textarea id="specs_text" name="specs_text" rows="10" placeholder='{"screen":"6.7 英寸","camera":"4800 万像素"}' class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">{{ old('specs_text', $specsText) }}</textarea>
    @error('specs_text')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
        保存手机
    </button>
    <a href="{{ route('products.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">返回列表</a>
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
                }
            });
        });
    </script>
@endonce
