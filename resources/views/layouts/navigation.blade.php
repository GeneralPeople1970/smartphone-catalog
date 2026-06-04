<nav x-data="{ open: false }" class="admin-top-nav sticky top-0 z-30 border-b border-gray-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                    <img src="{{ asset('assets/logo.png') }}" alt="智能手机参数站" class="h-8 w-8 rounded-sm">
                    <h1 class="font-semibold text-gray-900" style="font-size: 1.375rem; line-height: 1.75rem;">智能手机参数站后台</h1>
                </a>
            </div>

            <div class="admin-desktop-user hidden items-center text-base font-semibold text-gray-900">
                {{ Auth::user()->name }}
            </div>

            <div class="admin-mobile-toggle -me-2 flex items-center lg:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-hidden">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="admin-mobile-menu hidden lg:hidden">
        <div class="space-y-1 pb-3 pt-2">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                控制台
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                个人资料
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">
                手机管理
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('products.import')" :active="request()->routeIs('products.import')">
                批量导入
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('homepage.index')" :active="request()->routeIs('homepage.*')">
                热门管理
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('homepage-slides.index')" :active="request()->routeIs('homepage-slides.*')">
                轮播图管理
            </x-responsive-nav-link>
        </div>

        <div class="border-t border-gray-200 pb-1 pt-4">
            <div class="px-4">
                <div class="text-base font-semibold text-gray-900">{{ Auth::user()->name }}</div>
                <div class="text-sm font-medium text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button
                        type="submit"
                        class="block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-red-600 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700 focus:bg-red-50 focus:text-red-700 focus:outline-hidden"
                    >
                        退出登录
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
