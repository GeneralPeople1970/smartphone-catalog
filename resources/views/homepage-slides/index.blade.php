<x-app-layout>
    @section('title', '轮播图管理')

    <x-slot name="header">
        <div>
            <h1 class="admin-page-title">轮播图管理</h1>
            <p class="admin-page-subtitle">维护首页焦点图、跳转链接和展示顺序。</p>
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

            <form method="POST" action="{{ route('homepage-slides.store') }}" enctype="multipart/form-data" class="admin-panel">
                @csrf

                <div class="admin-panel-header">
                    <h2 class="admin-panel-title">上传轮播图</h2>
                </div>

                <div class="admin-panel-body">
                    <div class="admin-form-grid">
                        <div class="admin-field">
                            <label for="title">标题</label>
                            <input id="title" name="title" type="text" value="{{ old('title') }}" class="admin-input" placeholder="例如 首页焦点 1">
                        </div>

                        <div class="admin-field">
                            <label for="link_url">跳转链接</label>
                            <input id="link_url" name="link_url" type="text" value="{{ old('link_url') }}" class="admin-input">
                            <p class="admin-hint">可留空；填写后点击轮播图会跳转到该地址。</p>
                        </div>

                        <label class="admin-checkbox-field">
                            <input type="checkbox" name="is_active" value="1" class="admin-checkbox" checked>
                            上架
                        </label>

                        <div class="admin-field admin-field-wide">
                            <label for="image">图片</label>
                            <input id="image" name="image" type="file" accept="image/*" required class="admin-file-input">
                        </div>
                    </div>

                    <div class="admin-form-actions admin-form-actions-end mt-6">
                        <button type="submit" class="admin-button-primary">上传轮播图</button>
                    </div>
                </div>
            </form>

            <section class="admin-panel">
                <div class="admin-panel-header">
                    <h2 class="admin-panel-title">轮播图列表</h2>
                </div>

                <div class="admin-divide-y">
                    @forelse ($slides as $slide)
                        <div class="admin-row admin-row-slide">
                            <img src="{{ asset(ltrim($slide->image_path, '/')) }}" alt="{{ $slide->title ?: '首页轮播图' }}" class="admin-row-preview">

                            <form id="slide-update-{{ $slide->id }}" method="POST" action="{{ route('homepage-slides.update', $slide) }}" enctype="multipart/form-data" class="admin-row-form">
                                @csrf
                                @method('PUT')

                                <div class="admin-field">
                                    <label for="title-{{ $slide->id }}">标题</label>
                                    <input id="title-{{ $slide->id }}" name="title" type="text" value="{{ old('title', $slide->title) }}" class="admin-input">
                                </div>

                                <div class="admin-field">
                                    <label for="link-url-{{ $slide->id }}">跳转链接</label>
                                    <input id="link-url-{{ $slide->id }}" name="link_url" type="text" value="{{ old('link_url', $slide->link_url) }}" class="admin-input">
                                </div>

                                <label class="admin-checkbox-field">
                                    <input type="checkbox" name="is_active" value="1" class="admin-checkbox" @checked($slide->is_active)>
                                    上架
                                </label>

                                <div class="admin-field admin-row-form-full">
                                    <label for="image-{{ $slide->id }}">替换图片</label>
                                    <input id="image-{{ $slide->id }}" name="image" type="file" accept="image/*" class="admin-file-input">
                                    <p class="admin-hint break-all">{{ $slide->image_path }}</p>
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

                                <form method="POST" action="{{ route('homepage-slides.destroy', $slide) }}" onsubmit="return confirm('确认删除这张轮播图吗？图片文件也会一起删除。');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-button-danger">删除</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="admin-empty">暂无轮播图，请先上传图片。</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
