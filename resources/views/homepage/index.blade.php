<x-app-layout>
    @section('title', '热门管理')
    @php($creating = old('_featured_form', 'create') === 'create')

    <x-slot name="header">
        <div><h1 class="admin-page-title">热门管理</h1></div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container space-y-6">
            <x-admin-feedback />
            <section class="admin-panel">
                <div class="admin-panel-header">
                    <h2 class="admin-panel-title">添加热门机型</h2>
                    <span class="status-pill status-pill-active">当前上架 {{ $featuredPhones->where('is_active', true)->count() }} 台</span>
                </div>
                <form method="POST" action="{{ route('homepage.featured-phones.store') }}" class="admin-panel-body">
                    @csrf
                    <input type="hidden" name="_featured_form" value="create">
                    <div class="admin-form-grid">
                        <x-product-picker :selected="$creating ? $selectedProduct : null" />
                        <div class="admin-field">
                            <label for="title">展示标题</label>
                            <input id="title" name="title" type="text" value="{{ $creating ? old('title') : '' }}" maxlength="191" class="admin-input">
                            <p class="admin-hint">可选</p>
                        </div>
                        <x-admin-checkbox name="is_active" :checked="! $creating || ! $errors->any() || old('is_active')">上架</x-admin-checkbox>
                        <div class="admin-field admin-field-wide">
                            <label for="description">展示文案</label>
                            <input id="description" name="description" type="text" value="{{ $creating ? old('description') : '' }}" maxlength="500" class="admin-input">
                            <p class="admin-hint">可选</p>
                        </div>
                    </div>
                    <div class="admin-form-actions admin-form-actions-end mt-6">
                        <button type="submit" class="admin-button-primary">添加热门机型</button>
                    </div>
                </form>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-header"><h2 class="admin-panel-title">热门机型列表</h2></div>
                <div class="admin-divide-y">
                    @forelse ($featuredPhones as $featuredPhone)
                        @php($product = $featuredPhone->product)
                        @php($editing = (string) old('_featured_form') === (string) $featuredPhone->id)
                        <div class="admin-row">
                            <div class="admin-thumb admin-thumb-lg">
                                <img src="{{ $product?->safe_image_url ?? asset('assets/logo.png') }}" alt="{{ $product?->name }}" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('assets/logo.png') }}';">
                            </div>
                            <form id="featured-phone-{{ $featuredPhone->id }}" method="POST" action="{{ route('homepage.featured-phones.update', $featuredPhone) }}" class="admin-row-form">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="_featured_form" value="{{ $featuredPhone->id }}">
                                <div>
                                    <div class="admin-text-strong">#{{ $product?->id }} {{ $product?->brand }} - {{ $product?->name }}</div>
                                    <div class="admin-hint">{{ $product?->soc_name ?: '-' }}</div>
                                </div>
                                <div class="admin-field">
                                    <label for="title-{{ $featuredPhone->id }}">展示标题</label>
                                    <input id="title-{{ $featuredPhone->id }}" name="title" type="text" value="{{ $editing ? old('title') : $featuredPhone->title }}" maxlength="191" class="admin-input">
                                </div>
                                <x-admin-checkbox name="is_active" :checked="$editing ? old('is_active') : $featuredPhone->is_active">上架</x-admin-checkbox>
                                <div class="admin-field admin-row-form-full">
                                    <label for="description-{{ $featuredPhone->id }}">展示文案</label>
                                    <input id="description-{{ $featuredPhone->id }}" name="description" type="text" value="{{ $editing ? old('description') : $featuredPhone->description }}" maxlength="500" class="admin-input">
                                </div>
                            </form>
                            <div class="admin-row-actions">
                                <form method="POST" action="{{ route('homepage.featured-phones.move-up', $featuredPhone) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @disabled($loop->first) class="admin-button">上移</button>
                                </form>
                                <form method="POST" action="{{ route('homepage.featured-phones.move-down', $featuredPhone) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @disabled($loop->last) class="admin-button">下移</button>
                                </form>
                                <button type="submit" form="featured-phone-{{ $featuredPhone->id }}" class="admin-button-primary">保存</button>
                                <form method="POST" action="{{ route('homepage.featured-phones.destroy', $featuredPhone) }}" onsubmit="return confirm('确认移除这个热门机型吗？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-button-danger">删除</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="admin-empty">暂无热门机型</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
