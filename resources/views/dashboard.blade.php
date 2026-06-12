<x-app-layout>
    @section('title', '控制台')

    <x-slot name="header">
        <div class="admin-toolbar">
            <div>
                <h1 class="admin-page-title">控制台</h1>
                <p class="admin-page-subtitle">查看数据状态并进入常用管理流程。</p>
            </div>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container space-y-6">
            <div class="admin-stat-grid">
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
                <div class="admin-stat">
                    <span>首页内容</span>
                    <strong>{{ $activeFeaturedPhones + $activeHomepageSlides }}</strong>
                </div>
            </div>

            <div class="admin-dashboard-panels grid gap-6 lg:grid-cols-[1fr_360px]">
                <section class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <h2 class="text-base font-bold text-gray-900">常用入口</h2>
                            <p class="mt-1 text-sm text-gray-500">从这里进入主要数据维护页面。</p>
                        </div>
                    </div>
                    <div class="admin-panel-body admin-quick-actions">
                        <a href="{{ route('home') }}" class="admin-button-primary">返回首页</a>
                        <a href="{{ route('products.index') }}" class="admin-button">手机管理</a>
                        <a href="{{ route('products.import') }}" class="admin-button">批量导入</a>
                        <a href="{{ route('homepage.index') }}" class="admin-button">热门管理</a>
                        <a href="{{ route('homepage-slides.index') }}" class="admin-button">轮播图管理</a>
                    </div>
                </section>

                <section class="admin-panel">
                    <div class="admin-panel-header">
                        <h2 class="text-base font-bold text-gray-900">当前账号</h2>
                    </div>
                    <div class="admin-panel-body">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">用户名</dt>
                                <dd class="font-semibold text-gray-900">{{ Auth::user()->name }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">注册排名</dt>
                                <dd class="font-semibold text-gray-900">{{ $userRank }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">注册时间</dt>
                                <dd class="font-semibold text-gray-900">{{ Auth::user()->created_at?->format('Y-m-d H:i') }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">后台账号数</dt>
                                <dd class="font-semibold text-gray-900">{{ $totalUsers }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
