<x-app-layout>
    @section('title', '手机管理')

    <x-slot name="header">
        <div class="admin-toolbar">
            <div>
                <h1 class="admin-page-title">手机管理</h1>
                <p class="admin-page-subtitle">维护规范品牌、型号、图片、价格、处理器、电池和发布状态。</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('products.import') }}" class="admin-button">批量导入</a>
                <a href="{{ route('products.create') }}" class="admin-button-primary">新增手机</a>
            </div>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container space-y-6">
            @if (session('status'))
                <div class="admin-alert-success">{{ session('status') }}</div>
            @endif

            <div class="admin-stat-grid admin-stat-grid-three">
                <div class="admin-stat">
                    <span>全部手机</span>
                    <strong>{{ $totalProducts }}</strong>
                </div>
                <div class="admin-stat">
                    <span>已发布</span>
                    <strong class="admin-primary-text">{{ $publishedProducts }}</strong>
                </div>
                <div class="admin-stat">
                    <span>草稿</span>
                    <strong class="text-amber-700">{{ $draftProducts }}</strong>
                </div>
            </div>

            <section class="admin-panel">
                <form method="GET" action="{{ route('products.index') }}" class="grid items-end gap-3 border-b border-gray-200 p-4 md:grid-cols-[1fr_180px_auto_auto]">
                    <div class="admin-field">
                        <label for="keyword">关键词</label>
                        <input id="keyword" type="text" name="keyword" value="{{ request('keyword') }}" placeholder="品牌、手机名称、处理器或 ID" class="admin-input">
                    </div>
                    <div class="admin-field">
                        <label for="status">状态</label>
                        <select id="status" name="status" class="admin-select">
                            <option value="">全部状态</option>
                            <option value="draft" @selected(request('status') === 'draft')>草稿</option>
                            <option value="published" @selected(request('status') === 'published')>已发布</option>
                        </select>
                    </div>
                    <button type="submit" class="admin-button-primary">筛选</button>
                    @if ($hasActiveFilters)
                        <a href="{{ route('products.index') }}" class="admin-button">重置</a>
                    @endif
                </form>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>手机</th>
                                <th>品牌</th>
                                <th>处理器</th>
                                <th>电池</th>
                                <th>Slug</th>
                                <th>状态</th>
                                <th class="text-right">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    <td class="font-semibold text-gray-700">#{{ $product->id }}</td>
                                    <td>
                                        <div class="flex min-w-72 items-center gap-3">
                                            <div class="admin-thumb">
                                                @if ($product->image_url)
                                                    <img src="{{ $product->safe_image_url }}" alt="{{ $product->name }}" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('assets/phone-placeholder.svg') }}';">
                                                @else
                                                    无图
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-gray-900">{{ $product->name }}</div>
                                                <div class="mt-1 text-sm text-gray-500">{{ $product->display_price }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $product->brand }}</td>
                                    <td>{{ $product->soc_name ?: '-' }}</td>
                                    <td>{{ $product->battery_capacity ? $product->battery_capacity.' mAh' : '-' }}</td>
                                    <td class="max-w-52 truncate text-gray-500">{{ $product->slug ?: '-' }}</td>
                                    <td>
                                        <span class="status-pill {{ $product->status === 'published' ? 'status-pill-published' : 'status-pill-draft' }}">
                                            {{ $product->status === 'published' ? '已发布' : '草稿' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('products.edit', $product) }}" class="admin-button">编辑</a>
                                            <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('确认删除这个手机吗？该操作不可恢复。');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="admin-button-danger">删除</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="admin-empty">
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
            </section>
        </div>
    </div>
</x-app-layout>
