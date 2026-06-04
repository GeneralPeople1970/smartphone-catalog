<x-app-layout>
    @section('title', '轮播图管理')

    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">轮播图管理</h1>
            <p class="mt-1 text-sm text-gray-500">管理前端首页轮播图。</p>
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

            <form method="POST" action="{{ route('homepage-slides.store') }}" enctype="multipart/form-data" class="rounded-lg bg-white p-6 shadow-xs">
                @csrf

                <div class="grid gap-4 lg:grid-cols-[1fr_1fr_120px]">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">标题</label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="link_url" class="block text-sm font-medium text-gray-700">跳转链接</label>
                        <input id="link_url" name="link_url" type="text" value="{{ old('link_url') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500" placeholder="/XIAOMI 或 https://...">
                    </div>

                    <label class="flex items-end gap-2 pb-2 text-sm font-medium text-gray-700">
                        <input type="checkbox" name="is_active" value="1" class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500" checked>
                        上架
                    </label>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-700">图片</label>
                        <input id="image" name="image" type="file" accept="image/*" required class="mt-1 block w-full rounded-md border border-gray-300 text-sm text-gray-700 file:mr-4 file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-700">
                    </div>

                    <button type="submit" class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        上传轮播图
                    </button>
                </div>
            </form>

            <div class="rounded-lg bg-white shadow-xs">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">轮播图列表</h2>
                </div>

                <div class="divide-y divide-gray-200">
                    @forelse ($slides as $slide)
                        <div class="grid gap-4 p-4 lg:grid-cols-[220px_1fr_auto] lg:items-center">
                            <img src="{{ asset(ltrim($slide->image_path, '/')) }}" alt="{{ $slide->title ?: '首页轮播图' }}" class="h-28 w-full rounded-md border border-gray-200 object-cover lg:w-52">

                            <form id="slide-update-{{ $slide->id }}" method="POST" action="{{ route('homepage-slides.update', $slide) }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_1fr_100px]">
                                @csrf
                                @method('PUT')

                                <div>
                                    <label for="title-{{ $slide->id }}" class="block text-xs font-medium text-gray-500">标题</label>
                                    <input id="title-{{ $slide->id }}" name="title" type="text" value="{{ old('title', $slide->title) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                <div>
                                    <label for="link-url-{{ $slide->id }}" class="block text-xs font-medium text-gray-500">跳转链接</label>
                                    <input id="link-url-{{ $slide->id }}" name="link_url" type="text" value="{{ old('link_url', $slide->link_url) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                <label class="flex items-end gap-2 pb-2 text-sm font-medium text-gray-700">
                                    <input type="checkbox" name="is_active" value="1" class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500" @checked($slide->is_active)>
                                    上架
                                </label>

                                <div class="md:col-span-2 xl:col-span-3">
                                    <label for="image-{{ $slide->id }}" class="block text-xs font-medium text-gray-500">替换图片</label>
                                    <input id="image-{{ $slide->id }}" name="image" type="file" accept="image/*" class="mt-1 block w-full rounded-md border border-gray-300 text-sm text-gray-700 file:mr-4 file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200">
                                    <div class="mt-1 break-all text-xs text-gray-500">{{ $slide->image_path }}</div>
                                </div>
                            </form>

                            <div class="flex gap-3 lg:flex-col">
                                <form method="POST" action="{{ route('homepage-slides.move-up', $slide) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @disabled($loop->first) class="inline-flex w-full justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40">
                                        上移
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('homepage-slides.move-down', $slide) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @disabled($loop->last) class="inline-flex w-full justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40">
                                        下移
                                    </button>
                                </form>

                                <button type="submit" form="slide-update-{{ $slide->id }}" class="inline-flex justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                                    保存
                                </button>

                                <form method="POST" action="{{ route('homepage-slides.destroy', $slide) }}" onsubmit="return confirm('确认删除这张轮播图吗？图片文件也会一起删除。');">
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
                            暂无轮播图，请先上传图片。
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
