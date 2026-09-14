<x-app-layout>
    @section('title', '轮播图管理')

    @php($creating = old('_slide_form', 'create') === 'create')

    <x-slot name="header">
        <div>
            <h1 class="admin-page-title">轮播图管理</h1>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container space-y-6">
            <x-admin-feedback />

            <form method="POST" action="{{ route('homepage-slides.store') }}" enctype="multipart/form-data" class="admin-panel">
                @csrf
                <input type="hidden" name="_slide_form" value="create">

                <div class="admin-panel-header">
                    <h2 class="admin-panel-title">添加轮播图</h2>
                </div>

                <div class="admin-panel-body">
                    <div class="admin-form-grid">
                        <div class="admin-field">
                            <label for="title">标题</label>
                            <input id="title" name="title" type="text" value="{{ $creating ? old('title') : '' }}" class="admin-input" placeholder="可选">
                        </div>

                        <div class="admin-field">
                            <label for="link_url">跳转链接</label>
                            <input id="link_url" name="link_url" type="text" value="{{ $creating ? old('link_url') : '' }}" class="admin-input">
                            <p class="admin-hint">可选</p>
                        </div>

                        <x-admin-checkbox name="is_active" :checked="! $creating || ! $errors->any() || old('is_active')">上架</x-admin-checkbox>

                        <div class="admin-field admin-field-wide">
                            <label for="image_url">图片地址</label>
                            <input id="image_url" name="image_url" type="text" value="{{ $creating ? old('image_url') : '' }}" maxlength="2048" class="admin-input" placeholder="/assets/image.png 或 https://...">
                        </div>

                        <div class="admin-field">
                            <label for="image">或上传图片</label>
                            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="admin-file-input">
                        </div>
                    </div>

                    <div class="admin-form-actions admin-form-actions-end mt-6">
                        <button type="submit" class="admin-button-primary">添加轮播图</button>
                    </div>
                </div>
            </form>

            <section class="admin-panel">
                <div class="admin-panel-header">
                    <h2 class="admin-panel-title">轮播图列表</h2>
                </div>

                <div class="admin-divide-y">
                    @forelse ($slides as $slide)
                        @php($editing = (string) old('_slide_form') === (string) $slide->id)
                        <div class="admin-row admin-row-slide">
                            <img src="{{ \App\Support\ImageUrl::resolve($slide->image_path) }}" alt="{{ $slide->title ?: '首页轮播图' }}" class="admin-row-preview" onerror="this.onerror=null;this.src='{{ asset('assets/logo.png') }}';">

                            <form id="slide-update-{{ $slide->id }}" method="POST" action="{{ route('homepage-slides.update', $slide) }}" enctype="multipart/form-data" class="admin-row-form">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="_slide_form" value="{{ $slide->id }}">

                                <div class="admin-field">
                                    <label for="title-{{ $slide->id }}">标题</label>
                                    <input id="title-{{ $slide->id }}" name="title" type="text" value="{{ $editing ? old('title') : $slide->title }}" class="admin-input">
                                </div>

                                <div class="admin-field">
                                    <label for="link-url-{{ $slide->id }}">跳转链接</label>
                                    <input id="link-url-{{ $slide->id }}" name="link_url" type="text" value="{{ $editing ? old('link_url') : $slide->link_url }}" class="admin-input">
                                </div>

                                <x-admin-checkbox name="is_active" :checked="$editing ? old('is_active') : $slide->is_active">上架</x-admin-checkbox>

                                <div class="admin-field admin-row-form-full">
                                    <label for="image-url-{{ $slide->id }}">图片地址</label>
                                    <input id="image-url-{{ $slide->id }}" name="image_url" type="text" value="{{ $editing ? old('image_url') : $slide->image_path }}" maxlength="2048" class="admin-input">
                                </div>

                                <div class="admin-field admin-row-form-full">
                                    <label for="image-{{ $slide->id }}">或上传图片</label>
                                    <input id="image-{{ $slide->id }}" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="admin-file-input">
                                </div>
                            </form>

                            <div class="admin-row-actions">
                                <form method="POST" action="{{ route('homepage-slides.move-up', $slide) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @disabled($loop->first) class="admin-button">上移</button>
                                </form>

                                <form method="POST" action="{{ route('homepage-slides.move-down', $slide) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @disabled($loop->last) class="admin-button">下移</button>
                                </form>

                                <button type="submit" form="slide-update-{{ $slide->id }}" class="admin-button-primary">保存</button>

                                <form method="POST" action="{{ route('homepage-slides.destroy', $slide) }}" onsubmit="return confirm('确认删除这张轮播图吗？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-button-danger">删除</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="admin-empty">暂无轮播图</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
