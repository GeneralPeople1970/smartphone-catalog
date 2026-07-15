@php
    $sidebarLinks = [
        [
            'label' => '控制台',
            'href' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
            'icon' => 'M3 13h8V3H3v10Zm10 8h8V3h-8v18ZM5 11V5h4v6H5Zm10 8V5h4v14h-4ZM3 21h8v-6H3v6Zm2-2v-2h4v2H5Z',
        ],
        [
            'label' => '个人资料',
            'href' => route('profile.edit'),
            'active' => request()->routeIs('profile.edit'),
            'icon' => 'M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0-2a3 3 0 1 1 0-6 3 3 0 0 1 0 6Zm9 11a9 9 0 0 0-18 0h2a7 7 0 0 1 14 0h2Z',
        ],
        [
            'label' => '手机管理',
            'href' => route('products.index'),
            'active' => request()->routeIs('products.*'),
            'icon' => 'M7 6V5a5 5 0 0 1 10 0v1h3v15H4V6h3Zm2 0h6V5a3 3 0 0 0-6 0v1Zm-3 2v11h12V8H6Zm4 3h2v2h-2v-2Zm4 0h2v2h-2v-2Z',
        ],
        [
            'label' => '热门管理',
            'href' => route('homepage.index'),
            'active' => request()->routeIs('homepage.*'),
            'icon' => 'M3 21V9l9-7 9 7v12h-6v-7H9v7H3Zm2-2h2v-7h10v7h2V10l-7-5.4L5 10v9Z',
        ],
        [
            'label' => '轮播图管理',
            'href' => route('homepage-slides.index'),
            'active' => request()->routeIs('homepage-slides.*'),
            'icon' => 'M4 5h16v12H4V5Zm2 2v8h12V7H6Zm2 6 2.5-3 2 2.4 1.5-1.8 2 2.4H8Zm-4 6h16v2H4v-2Z',
        ],
    ];

    if (auth()->user()?->canManageUsers()) {
        $sidebarLinks[] = [
            'label' => '用户管理',
            'href' => route('users.index'),
            'active' => request()->routeIs('users.*'),
            'icon' => 'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3Zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3Zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5Zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5Z',
        ];
    }
@endphp

<aside class="admin-sidebar hidden w-64 shrink-0 lg:block">
    <div class="admin-sidebar-sticky flex flex-col px-4 py-6">
        <div>
            <div class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">后台管理</div>

            <nav class="mt-4 space-y-1">
                @foreach ($sidebarLinks as $link)
                    <a href="{{ $link['href'] }}" class="admin-sidebar-link {{ $link['active'] ? 'admin-sidebar-link-active' : '' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="{{ $link['icon'] }}" />
                        </svg>
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>

        <div class="mt-auto border-t border-gray-200 pt-4">
            <div class="px-3 pb-3">
                <div class="block truncate text-sm font-medium text-gray-900">{{ Auth::user()->name }}</div>
                <div class="truncate text-xs text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="admin-sidebar-link w-full text-red-600 hover:bg-red-50 hover:text-red-700"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M10 3h10v18H10v-2h8V5h-8V3Zm1 5 1.4 1.4L10.8 11H3v2h7.8l1.6 1.6L11 16l-4-4 4-4Z" />
                    </svg>
                    <span>退出登录</span>
                </button>
            </form>
        </div>
    </div>
</aside>
