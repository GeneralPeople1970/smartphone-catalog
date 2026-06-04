<x-app-layout>
    @section('title', '热门管理')

    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">热门管理</h1>
            <p class="mt-1 text-sm text-gray-500">管理前端“热门机型”区域展示的手机型号。</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <div class="font-medium">提交失败，请检查下面的问题。</div>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-xs">
                <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">添加热门机型</h2>
                    </div>
                    <div class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">
                        当前上架：<span id="active_featured_count">{{ $featuredPhones->where('is_active', true)->count() }}</span> 台
                    </div>
                </div>

                <form method="POST" action="{{ route('homepage.featured-phones.store') }}">
                    @csrf

                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_100px]">
                        <div>
                            <label for="product_search" class="block text-sm font-medium text-gray-700">手机型号</label>
                            <input
                                id="product_search"
                                type="search"
                                autocomplete="off"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="输入型号、品牌、处理器或 ID 搜索"
                            >
                            <select
                                id="product_id"
                                name="product_id"
                                required
                                data-old-value="{{ old('product_id') }}"
                                class="mt-2 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                            >
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

                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">展示标题</label>
                            <input id="title" name="title" type="text" value="{{ old('title') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500" placeholder="不填则使用手机型号">
                        </div>

                        <label class="flex items-end gap-2 pb-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" name="is_active" value="1" class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500" checked>
                            上架
                        </label>
                    </div>

                    <div class="mt-4">
                        <label for="description" class="block text-sm font-medium text-gray-700">展示文案</label>
                        <input id="description" name="description" type="text" value="{{ old('description') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500" placeholder="不填则使用手机卖点">
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button type="submit" class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            添加热门机型
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-lg bg-white shadow-xs">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">热门机型</h2>
                </div>

                <div class="divide-y divide-gray-200">
                    @forelse ($featuredPhones as $featuredPhone)
                        @php($product = $featuredPhone->product)
                        <div class="featured-phone-row">
                            <div class="h-20 w-20 overflow-hidden rounded-md border border-gray-200 bg-gray-50">
                                @if ($product?->image_url)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover" loading="lazy">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">无图</div>
                                @endif
                            </div>

                            <form id="featured-phone-{{ $featuredPhone->id }}" method="POST" action="{{ route('homepage.featured-phones.update', $featuredPhone) }}" class="featured-phone-form grid gap-3 md:grid-cols-2">
                                @csrf
                                @method('PUT')

                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        #{{ $product?->id }} {{ $product?->brand }} - {{ $product?->name }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $product?->soc_name ?: '-' }}</div>
                                </div>

                                <div>
                                    <label for="title-{{ $featuredPhone->id }}" class="block text-xs font-medium text-gray-500">展示标题</label>
                                    <input id="title-{{ $featuredPhone->id }}" name="title" type="text" value="{{ old('title', $featuredPhone->title) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                <label class="flex items-end gap-2 pb-2 text-sm font-medium text-gray-700">
                                    <input type="checkbox" name="is_active" value="1" data-featured-active class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500" @checked($featuredPhone->is_active)>
                                    上架
                                </label>

                                <div class="md:col-span-2 xl:col-span-3">
                                    <label for="description-{{ $featuredPhone->id }}" class="block text-xs font-medium text-gray-500">展示文案</label>
                                    <input id="description-{{ $featuredPhone->id }}" name="description" type="text" value="{{ old('description', $featuredPhone->description) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </form>

                            <div class="featured-phone-actions">
                                <form method="POST" action="{{ route('homepage.featured-phones.move-up', $featuredPhone) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @disabled($loop->first) class="inline-flex w-full justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40">
                                        上移
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('homepage.featured-phones.move-down', $featuredPhone) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @disabled($loop->last) class="inline-flex w-full justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40">
                                        下移
                                    </button>
                                </form>

                                <button type="submit" form="featured-phone-{{ $featuredPhone->id }}" class="inline-flex w-full justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                                    保存
                                </button>

                                <form method="POST" action="{{ route('homepage.featured-phones.destroy', $featuredPhone) }}" onsubmit="return confirm('确认移除这个热门机型吗？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex w-full justify-center rounded-md border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                                        删除
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                            <div class="px-6 py-10 text-center text-sm text-gray-500">
                            暂无热门机型。没有上架数据时，前端会自动隐藏“热门机型”这一栏。
                        </div>
                    @endforelse
                </div>
            </div>
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

            if (!searchInput || !select) {
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
                iphone: ['苹果', 'iphone'],
                ipad: ['苹果', 'ipad'],
                xiaomi: ['小米'],
                mi: ['小米'],
                redmi: ['红米'],
                huawei: ['华为'],
                honor: ['荣耀'],
                samsung: ['三星'],
                oppo: ['oppo'],
                vivo: ['vivo'],
                oneplus: ['一加'],
                realme: ['真我'],
                meizu: ['魅族'],
                sony: ['索尼'],
                nokia: ['诺基亚'],
                motorola: ['摩托罗拉'],
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
                    node.selected = option.value === currentValue || (!currentValue && option.selected);
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
                grid-template-columns: 96px minmax(0, 1fr) 96px;
                align-items: start;
            }

            .featured-phone-form {
                min-width: 0;
            }

            .featured-phone-actions {
                width: 96px;
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
