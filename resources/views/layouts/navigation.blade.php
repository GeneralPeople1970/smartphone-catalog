@php
    $navUser = auth()->user();

    // Menu items are role-gated for UX only; every backend route keeps its
    // auth + active + role middleware and per-action Policy checks.
    $adminNavLinks = [
        [
            'label' => '控制台',
            'href' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
        ],
    ];

    if ($navUser?->canAccessAdmin()) {
        $adminNavLinks = [
            ...$adminNavLinks,
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
    }

    if ($navUser?->canManageUsers()) {
        $adminNavLinks[] = [
            'label' => '用户管理',
            'href' => route('users.index'),
            'active' => request()->routeIs('users.*'),
        ];

        $adminNavLinks[] = [
            'label' => '站点设置',
            'href' => route('settings.edit'),
            'active' => request()->routeIs('settings.*'),
        ];
    }

    $adminNavLinks[] = [
        'label' => '个人资料',
        'href' => route('profile.edit'),
        'active' => request()->routeIs('profile.edit'),
    ];

    $navBrandHref = route('dashboard');

    // The username chip is the front/back switch: in the backend it jumps to
    // the public site, and the frontend NavBar sends it back to /dashboard.
    $navSwitchHref = route('home');
@endphp

<nav x-data="{ open: false }" class="shared-nav-shell">
    <div class="shared-top-nav">
        <div class="shared-nav-container shared-top-nav-row">
            <a href="{{ $navBrandHref }}" class="shared-nav-brand">
                <img src="{{ asset('assets/logo.png') }}" alt="智能手机参数站" class="shared-nav-logo">
                <span>智能手机参数站</span>
            </a>

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

            <div class="shared-desktop-actions">
                <a href="{{ $navSwitchHref }}" class="shared-user-chip" title="前往前台首页">{{ $navUser->name }}</a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="shared-nav-logout">退出登录</button>
                </form>
            </div>
        </div>
    </div>

    <div class="shared-main-nav">
        <div class="shared-nav-container">
            <div :class="{'shared-nav-content-open': open}" class="shared-nav-content">
                {{-- Same order as the frontend NavBar: username chip, then the
                     logout button, in both the desktop bar and this menu. --}}
                <div class="shared-mobile-actions">
                    <a href="{{ $navSwitchHref }}" class="shared-user-chip" title="前往前台首页">{{ $navUser->name }}</a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="shared-nav-logout">退出登录</button>
                    </form>
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
            </div>
        </div>
    </div>
</nav>
