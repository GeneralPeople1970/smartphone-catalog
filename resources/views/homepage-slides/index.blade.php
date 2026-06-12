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
                    <h2 class="text-base font-bold text-gray-900">上传轮播图</h2>
                </div>

                <div class="admin-panel-body space-y-4">
                    <div class="grid gap-4 lg:grid-cols-[1fr_1fr_120px]">
                        <div class="admin-field">
                            <label for="title">标题</label>
                            <input id="title" name="title" type="text" value="{{ old('title') }}" class="admin-input" placeholder="例如 首页焦点 1">
                        </div>

                        <div class="admin-field">
                            <label for="link_url">跳转链接</label>
                            <input id="link_url" name="link_url" type="text" value="{{ old('link_url') }}" class="admin-input" placeholder="跳转链接（可选）">
                        </div>

                        <label class="flex items-end gap-2 pb-2 text-sm font-bold text-gray-700">
                            <input type="checkbox" name="is_active" value="1" class="admin-checkbox rounded-sm border-gray-300" checked>
                            上架
                        </label>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div class="admin-field">
                            <label for="image">图片</label>
                            <input id="image" name="image" type="file" accept="image/*" required class="admin-file-input">
                        </div>

                        <button type="submit" class="admin-button-primary">上传轮播图</button>
                    </div>
                </div>
            </form>

            <section class="admin-panel">
                <div class="admin-panel-header">
                    <h2 class="text-base font-bold text-gray-900">轮播图列表</h2>
                </div>

                <div class="divide-y divide-gray-200">
                    @forelse ($slides as $slide)
                        <div class="slide-row">
                            <img src="{{ asset(ltrim($slide->image_path, '/')) }}" alt="{{ $slide->title ?: '首页轮播图' }}" class="slide-preview">

                            <form id="slide-update-{{ $slide->id }}" method="POST" action="{{ route('homepage-slides.update', $slide) }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_1fr_100px]">
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

                                <label class="flex items-end gap-2 pb-2 text-sm font-bold text-gray-700">
                                    <input type="checkbox" name="is_active" value="1" class="admin-checkbox rounded-sm border-gray-300" @checked($slide->is_active)>
                                    上架
                                </label>

                                <div class="admin-field md:col-span-2 xl:col-span-3">
                                    <label class="admin-label" for="image-{{ $slide->id }}">替换图片</label>
                                    <input id="image-{{ $slide->id }}" name="image" type="file" accept="image/*" class="admin-file-input">
                                    <div class="mt-1 break-all text-xs text-gray-500">{{ $slide->image_path }}</div>
                                </div>
                            </form>

                            <div class="slide-actions">
                                <form method="POST" action="{{ route('homepage-slides.move-up', $slide) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @disabled($loop->first) class="admin-button w-full disabled:cursor-not-allowed disabled:opacity-40">上移</button>
                                </form>

                                <form method="POST" action="{{ route('homepage-slides.move-down', $slide) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @disabled($loop->last) class="admin-button w-full disabled:cursor-not-allowed disabled:opacity-40">下移</button>
                                </form>

                                <button type="submit" form="slide-update-{{ $slide->id }}" class="admin-button-primary w-full">保存</button>

                                <form method="POST" action="{{ route('homepage-slides.destroy', $slide) }}" onsubmit="return confirm('确认删除这张轮播图吗？图片文件也会一起删除。');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-button-danger w-full">删除</button>
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

    <style>
        .slide-row {
            display: grid;
            gap: 1rem;
            padding: 1rem;
        }

        .slide-preview {
            width: 100%;
            height: 9rem;
            border: 1px solid #dfe5df;
            border-radius: 0.5rem;
            object-fit: cover;
        }

        .slide-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        @media (min-width: 1024px) {
            .slide-row {
                grid-template-columns: 220px minmax(0, 1fr) 104px;
                align-items: start;
            }

            .slide-actions {
                width: 104px;
                flex-direction: column;
            }
        }
    </style>
</x-app-layout>
