<x-app-layout>
    @section('title', '控制台')

    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">控制台</h1>
            <p class="mt-1 text-sm text-gray-500">查看手机数据状态，进入后台管理常用功能。</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg bg-white p-6 shadow-xs">
                    <div class="text-sm font-medium text-gray-500">全部手机</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $totalProducts }}</div>
                    <div class="mt-2 text-sm text-gray-500">已导入手机参数库</div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-xs">
                    <div class="text-sm font-medium text-gray-500">已发布</div>
                    <div class="mt-2 text-2xl font-semibold text-green-600">{{ $publishedProducts }}</div>
                    <div class="mt-2 text-sm text-gray-500">前台可调用的数据</div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-xs">
                    <div class="text-sm font-medium text-gray-500">草稿</div>
                    <div class="mt-2 text-2xl font-semibold text-amber-600">{{ $draftProducts }}</div>
                    <div class="mt-2 text-sm text-gray-500">暂不发布的数据</div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-xs">
                    <div class="text-sm font-medium text-gray-500">热门管理</div>
                    <div class="mt-2 text-2xl font-semibold text-blue-600">{{ $activeFeaturedPhones }}</div>
                    <div class="mt-2 text-sm text-gray-500">首页热门上架数量</div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-xs">
                    <div class="text-sm font-medium text-gray-500">轮播图</div>
                    <div class="mt-2 text-2xl font-semibold text-indigo-600">{{ $activeHomepageSlides }}</div>
                    <div class="mt-2 text-sm text-gray-500">首页当前上架数量</div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
                <div class="rounded-lg bg-white p-6 shadow-xs">
                    <h2 class="text-base font-semibold text-gray-900">常用入口</h2>

                    <div class="mt-4 border-t border-gray-200 pt-4">
                        <p class="text-sm text-gray-600">集中进入前台预览和后台管理功能。</p>
                    </div>

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('home') }}" class="inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-semibold text-white" style="background-color: #007bff;">
                            返回首页
                        </a>

                        <a href="{{ route('products.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            手机管理
                        </a>

                        <a href="{{ route('products.import') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            批量导入
                        </a>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-xs">
                    <h2 class="text-base font-semibold text-gray-900">当前账号</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">用户名</dt>
                            <dd class="font-medium text-gray-900">{{ Auth::user()->name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">注册排名</dt>
                            <dd class="font-medium text-gray-900">{{ $userRank }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">注册时间</dt>
                            <dd class="font-medium text-gray-900">{{ Auth::user()->created_at?->format('Y-m-d H:i') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">后台账号数</dt>
                            <dd class="font-medium text-gray-900">{{ $totalUsers }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
