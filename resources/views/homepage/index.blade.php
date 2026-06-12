<x-app-layout>
    @section('title', '热门管理')

    <x-slot name="header">
        <div>
            <h1 class="admin-page-title">热门管理</h1>
            <p class="admin-page-subtitle">维护首页热门机型的展示顺序、标题和文案。</p>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container space-y-6">
            @if (session('status'))
                <div class="admin-alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="admin-alert-danger">
                    <div class="font-bold">提交失败，请检查下面的问题。</div>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="admin-panel">
                <div class="admin-panel-header">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">添加热门机型</h2>
                        <p class="mt-1 text-sm text-gray-500">标题和文案留空时会使用清洗后的默认值。</p>
                    </div>
                    <span class="status-pill status-pill-active">当前上架 <span id="active_featured_count" class="ml-1">{{ $featuredPhones->where('is_active', true)->count() }}</span> 台</span>
                </div>

                <form method="POST" action="{{ route('homepage.featured-phones.store') }}" class="admin-panel-body space-y-4">
                    @csrf

                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)_120px]">
                        <div class="admin-field">
                            <label for="product_search">手机型号</label>
                            <input id="product_search" type="search" autocomplete="off" class="admin-input" placeholder="输入型号、品牌、处理器或 ID 搜索">
                            <select id="product_id" name="product_id" required data-old-value="{{ old('product_id') }}" class="admin-select mt-2">
                                <option value="">选择要加入热门的手机</option>
                                @foreach ($products as $product)
                                    <option
                                        value="{{ $product->id }}"
                                        data-search="{{ '#'.$product->id.' '.$product->brand.' '.$product->name.' '.$product->soc_name }}"
                                        @selected((string) old('product_id') === (string) $product->id)
                                    >
                                        #{{ $product->id }} {{ $product->brand }} - {{ $product->name }}{{ $product->soc_name ? ' / '.$product->soc_name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="admin-field">
                            <label for="title">展示标题</label>
                            <input id="title" name="title" type="text" value="{{ old('title') }}" class="admin-input" placeholder="不填则使用手机型号">
                        </div>

                        <label class="flex items-end gap-2 pb-2 text-sm font-bold text-gray-700">
                            <input type="checkbox" name="is_active" value="1" class="admin-checkbox rounded-sm border-gray-300" checked>
                            上架
                        </label>
                    </div>

                    <div class="admin-field">
                        <label for="description">展示文案</label>
                        <input id="description" name="description" type="text" value="{{ old('description') }}" class="admin-input" placeholder="不填则使用手机卖点或核心参数">
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="admin-button-primary">添加热门机型</button>
                    </div>
                </form>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-header">
                    <h2 class="text-base font-bold text-gray-900">热门机型列表</h2>
                </div>

                <div class="divide-y divide-gray-200">
                    @forelse ($featuredPhones as $featuredPhone)
                        @php($product = $featuredPhone->product)
                        <div class="featured-phone-row">
                            <div class="admin-thumb h-24 w-24">
                                @if ($product?->image_url)
                                    <img src="{{ $product->safe_image_url }}" alt="{{ $product->name }}" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('assets/phone-placeholder.svg') }}';">
                                @else
                                    无图
                                @endif
                            </div>

                            <form id="featured-phone-{{ $featuredPhone->id }}" method="POST" action="{{ route('homepage.featured-phones.update', $featuredPhone) }}" class="featured-phone-form grid gap-3 md:grid-cols-2">
                                @csrf
                                @method('PUT')

                                <div>
                                    <div class="text-sm font-bold text-gray-900">#{{ $product?->id }} {{ $product?->brand }} - {{ $product?->name }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $product?->soc_name ?: '-' }}</div>
                                </div>

                                <div class="admin-field">
                                    <label for="title-{{ $featuredPhone->id }}">展示标题</label>
                                    <input id="title-{{ $featuredPhone->id }}" name="title" type="text" value="{{ old('title', $featuredPhone->title) }}" class="admin-input">
                                </div>

                                <label class="flex items-end gap-2 pb-2 text-sm font-bold text-gray-700">
                                    <input type="checkbox" name="is_active" value="1" data-featured-active class="admin-checkbox rounded-sm border-gray-300" @checked($featuredPhone->is_active)>
                                    上架
                                </label>

                                <div class="admin-field md:col-span-2 xl:col-span-3">
                                    <label for="description-{{ $featuredPhone->id }}">展示文案</label>
                                    <input id="description-{{ $featuredPhone->id }}" name="description" type="text" value="{{ old('description', $featuredPhone->description) }}" class="admin-input">
                                </div>
                            </form>

                            <div class="featured-phone-actions">
                                <form method="POST" action="{{ route('homepage.featured-phones.move-up', $featuredPhone) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @disabled($loop->first) class="admin-button w-full disabled:cursor-not-allowed disabled:opacity-40">上移</button>
                                </form>

                                <form method="POST" action="{{ route('homepage.featured-phones.move-down', $featuredPhone) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @disabled($loop->last) class="admin-button w-full disabled:cursor-not-allowed disabled:opacity-40">下移</button>
                                </form>

                                <button type="submit" form="featured-phone-{{ $featuredPhone->id }}" class="admin-button-primary w-full">保存</button>

                                <form method="POST" action="{{ route('homepage.featured-phones.destroy', $featuredPhone) }}" onsubmit="return confirm('确认移除这个热门机型吗？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-button-danger w-full">删除</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="admin-empty">暂无热门机型。没有上架数据时，前端会自动隐藏热门机型区域。</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <script>
        (() => {
            const activeCount = document.getElementById('active_featured_count');
            const activeCheckboxes = Array.from(document.querySelectorAll('[data-featured-active]'));
            const searchInput = document.getElementById('product_search');
            const select = document.getElementById('product_id');

            if (activeCount) {
                const updateActiveCount = () => {
                    activeCount.textContent = activeCheckboxes.filter((checkbox) => checkbox.checked).length;
                };

                activeCheckboxes.forEach((checkbox) => {
                    checkbox.addEventListener('change', updateActiveCount);
                });

                updateActiveCount();
            }

            if (! searchInput || ! select) {
                return;
            }

            const placeholder = select.options[0].cloneNode(true);
            const options = Array.from(select.options)
                .slice(1)
                .map((option) => ({
                    value: option.value,
                    text: option.textContent.trim(),
                    search: option.dataset.search || option.textContent,
                    selected: option.selected,
                }));
            const oldValue = select.dataset.oldValue || '';

            const normalize = (value) => String(value || '')
                .toLowerCase()
                .replace(/[\s\-_/（）()【】[\].,，。:：]+/g, '');

            const aliasMap = {
                apple: ['苹果', 'iphone', 'ipad'],
                苹果: ['apple', 'iphone', 'ipad'],
                iphone: ['苹果', 'iphone'],
                ipad: ['苹果', 'ipad'],
                xiaomi: ['小米'],
                小米: ['xiaomi', 'mi'],
                mi: ['小米'],
                redmi: ['红米'],
                红米: ['redmi'],
                huawei: ['华为'],
                华为: ['huawei'],
                honor: ['荣耀'],
                荣耀: ['honor'],
                samsung: ['三星'],
                三星: ['samsung'],
                oppo: ['oppo'],
                vivo: ['vivo'],
                oneplus: ['一加'],
                一加: ['oneplus'],
                realme: ['真我'],
                真我: ['realme'],
                meizu: ['魅族'],
                魅族: ['meizu'],
                lenovo: ['联想', 'zuk'],
                联想: ['lenovo', 'zuk'],
                联想小新: ['lenovo', 'zuk'],
                sony: ['索尼'],
                索尼: ['sony'],
                nokia: ['诺基亚'],
                诺基亚: ['nokia'],
                motorola: ['摩托罗拉'],
                摩托罗拉: ['motorola', 'moto'],
                snapdragon: ['骁龙', '高通'],
                qualcomm: ['骁龙', '高通'],
                dimensity: ['天玑', '联发科'],
                mediatek: ['天玑', '联发科'],
                kirin: ['麒麟'],
            };

            const queryTerms = (query) => {
                const normalizedQuery = normalize(query);
                const aliases = aliasMap[normalizedQuery] || [];

                return [normalizedQuery, ...aliases.map(normalize)].filter(Boolean);
            };

            const renderOptions = () => {
                const query = searchInput.value.trim();
                const terms = queryTerms(query);
                const currentValue = select.value || oldValue;
                const filtered = terms.length
                    ? options.filter((option) => {
                        const text = option.search.toLowerCase();
                        const normalizedSearch = normalize(option.search);

                        return text.includes(query.toLowerCase()) || terms.some((term) => normalizedSearch.includes(term));
                    })
                    : options;

                select.innerHTML = '';
                select.appendChild(placeholder.cloneNode(true));

                const visibleOptions = terms.length ? filtered.slice(0, 200) : filtered;

                visibleOptions.forEach((option) => {
                    const node = document.createElement('option');
                    node.value = option.value;
                    node.textContent = option.text;
                    node.dataset.search = option.search;
                    node.selected = option.value === currentValue || (! currentValue && option.selected);
                    select.appendChild(node);
                });
            };

            searchInput.addEventListener('input', renderOptions);
            renderOptions();
        })();
    </script>

    <style>
        .featured-phone-row {
            display: grid;
            gap: 1rem;
            padding: 1rem;
        }

        .featured-phone-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        @media (min-width: 1024px) {
            .featured-phone-row {
                grid-template-columns: 112px minmax(0, 1fr) 104px;
                align-items: start;
            }

            .featured-phone-actions {
                width: 104px;
                flex-direction: column;
            }
        }

        @media (min-width: 1280px) {
            .featured-phone-form {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 90px;
            }
        }
    </style>
</x-app-layout>
