<x-app-layout>
    @section('title', '手机管理')

    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">手机管理</h1>
                <p class="mt-1 text-sm text-gray-500">维护手机的参数和发布状态。</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('products.import') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    批量导入
                </a>
                <a href="{{ route('products.create') }}" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    新增手机
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg bg-white p-5 shadow-xs">
                    <div class="text-sm text-gray-500">全部手机</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $totalProducts }}</div>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-xs">
                    <div class="text-sm text-gray-500">已发布</div>
                    <div class="mt-2 text-2xl font-semibold text-green-600">{{ $publishedProducts }}</div>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-xs">
                    <div class="text-sm text-gray-500">草稿</div>
                    <div class="mt-2 text-2xl font-semibold text-amber-600">{{ $draftProducts }}</div>
                </div>
            </div>

            <div class="mt-6 rounded-lg bg-white shadow-xs">
                <form method="GET" action="{{ route('products.index') }}" class="grid items-start gap-3 border-b border-gray-200 p-4 md:grid-cols-[1fr_180px_auto_auto]">
                    <input
                        type="text"
                        name="keyword"
                        value="{{ request('keyword') }}"
                        placeholder="搜索品牌、手机名称、处理器或 ID"
                        class="w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <select name="status" class="rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">全部状态</option>
                        <option value="draft" @selected(request('status') === 'draft')>草稿</option>
                        <option value="published" @selected(request('status') === 'published')>已发布</option>
                    </select>
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                        筛选
                    </button>
                    @if ($hasActiveFilters)
                        <a href="{{ route('products.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            重置
                        </a>
                    @endif
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">手机</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">品牌</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">处理器</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">电池</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">状态</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($products as $product)
                                <tr>
                                    <td class="px-4 py-4 text-sm font-medium text-gray-700">{{ $product->id }}</td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-14 w-14 shrink-0 overflow-hidden rounded-md border border-gray-200 bg-gray-50">
                                                @if ($product->image_url)
                                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover" loading="lazy">
                                                @else
                                                    <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">无图</div>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-900">{{ $product->name }}</div>
                                                <div class="mt-1 text-sm text-gray-500">{{ $product->display_price }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700">{{ $product->brand }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-700">{{ $product->soc_name ?: '-' }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-700">
                                        {{ $product->battery_capacity ? $product->battery_capacity.' mAh' : '-' }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $product->status === 'published' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">
                                            {{ $product->status === 'published' ? '已发布' : '草稿' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm">
                                        <a href="{{ route('products.edit', $product) }}" class="font-medium text-indigo-600 hover:text-indigo-900">编辑</a>
                                        <form method="POST" action="{{ route('products.destroy', $product) }}" class="inline" onsubmit="return confirm('确认删除这个手机吗？');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ml-3 font-medium text-red-600 hover:text-red-900">删除</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                                        @if ($hasActiveFilters)
                                            没有找到符合条件的手机，请调整筛选条件。
                                        @else
                                            暂无手机数据。
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($products->hasPages())
                    <div class="border-t border-gray-200 p-4">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
