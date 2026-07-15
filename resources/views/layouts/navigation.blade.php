@php
    $adminNavLinks = [
        [
            'label' => '控制台',
            'href' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
        ],
        [
            'label' => '手机管理',
            'href' => route('products.index'),
            'active' => request()->routeIs('products.*'),
        ],
        [
            'label' => '热门管理',
            'href' => route('homepage.index'),
            'active' => request()->routeIs('homepage.*'),
        ],
        [
            'label' => '轮播图管理',
            'href' => route('homepage-slides.index'),
            'active' => request()->routeIs('homepage-slides.*'),
        ],
    ];

    if (auth()->user()?->canManageUsers()) {
        $adminNavLinks[] = [
            'label' => '用户管理',
            'href' => route('users.index'),
            'active' => request()->routeIs('users.*'),
        ];
    }

    $adminNavLinks[] = [
        'label' => '个人资料',
        'href' => route('profile.edit'),
        'active' => request()->routeIs('profile.edit'),
    ];
@endphp

<nav x-data="{ open: false }" class="shared-nav-shell">
    <div class="shared-top-nav">
        <div class="shared-nav-container shared-top-nav-row">
            <a href="{{ route('dashboard') }}" class="shared-nav-brand">
                <img src="{{ asset('assets/logo.png') }}" alt="智能手机参数站" class="shared-nav-logo">
                <span>智能手机参数站</span>
            </a>

            <div class="shared-desktop-actions">
                <a href="{{ route('home') }}" class="shared-user-chip">{{ Auth::user()->name }}</a>
                <x-theme-control />
            </div>

            <button
                type="button"
                @click="open = ! open"
                class="shared-nav-toggle"
                aria-label="切换后台导航"
                :aria-expanded="open ? 'true' : 'false'"
            >
                <svg class="shared-nav-toggle-icon" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="open ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'" />
                </svg>
            </button>
        </div>
    </div>

    <div class="shared-main-nav">
        <div class="shared-nav-container">
            <div :class="{'shared-nav-content-open': open}" class="shared-nav-content">
                <div class="shared-mobile-actions">
                    <a href="{{ route('home') }}" class="shared-user-chip">{{ Auth::user()->name }}</a>
                    <x-theme-control />
                </div>

                <ul class="shared-nav-menu">
                    @foreach ($adminNavLinks as $link)
                        <li class="shared-nav-item">
                            <a href="{{ $link['href'] }}" class="shared-nav-link {{ $link['active'] ? 'admin-main-nav-link-active' : '' }}">
                                {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="shared-mobile-meta">
                    <div class="truncate">{{ Auth::user()->email }}</div>
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="admin-button-danger w-full">退出登录</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</nav>
