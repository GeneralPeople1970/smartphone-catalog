<x-app-layout>
    @section('title', '控制台')

    <x-slot name="header">
        <div class="admin-toolbar">
            <div>
                <h1 class="admin-page-title">控制台</h1>
                <p class="admin-page-subtitle">查看数据状态并进入当前账号可用的功能。</p>
            </div>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container space-y-6">
            @if (Auth::user()->canAccessAdmin())
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
                        <strong class="admin-warning-text">{{ $draftProducts }}</strong>
                    </div>
                    <div class="admin-stat">
                        <span>首页内容</span>
                        <strong>{{ $activeFeaturedPhones + $activeHomepageSlides }}</strong>
                    </div>
                </div>
            @endif

            <div class="admin-dashboard-panels grid gap-6 lg:grid-cols-[1fr_360px]">
                <section class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <h2 class="admin-panel-title">常用入口</h2>
                            <p class="admin-panel-note">从这里进入当前账号可用的页面。</p>
                        </div>
                    </div>
                    <div class="admin-panel-body admin-quick-actions">
                        <a href="{{ route('home') }}" class="admin-button-primary">返回首页</a>
                        <a href="{{ route('profile.edit') }}" class="admin-button">个人资料</a>
                        @if (Auth::user()->canAccessAdmin())
                            <a href="{{ route('products.index') }}" class="admin-button">手机管理</a>
                            <a href="{{ route('products.import') }}" class="admin-button">批量导入</a>
                            <a href="{{ route('homepage.index') }}" class="admin-button">热门管理</a>
                            <a href="{{ route('homepage-slides.index') }}" class="admin-button">轮播图管理</a>
                        @endif
                    </div>
                </section>

                <section class="admin-panel">
                    <div class="admin-panel-header">
                        <h2 class="admin-panel-title">当前账号</h2>
                    </div>
                    <div class="admin-panel-body">
                        <dl class="admin-meta-list">
                            <div class="admin-meta-row">
                                <dt>用户名</dt>
                                <dd>{{ Auth::user()->name }}</dd>
                            </div>
                            <div class="admin-meta-row">
                                <dt>注册排名</dt>
                                <dd>{{ $userRank }}</dd>
                            </div>
                            <div class="admin-meta-row">
                                <dt>注册时间</dt>
                                <dd>{{ Auth::user()->created_at?->format('Y-m-d H:i') }}</dd>
                            </div>
                            <div class="admin-meta-row">
                                <dt>账号角色</dt>
                                <dd>{{ Auth::user()->role->label() }}</dd>
                            </div>
                            <div class="admin-meta-row">
                                <dt>后台账号数</dt>
                                <dd>{{ $totalUsers }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
