<nav x-data="{ open: false }" class="admin-top-nav sticky top-0 z-30">
    <div class="admin-container">
        <div class="admin-top-nav-row">
            <a href="{{ route('dashboard') }}" class="admin-brand">
                <img src="{{ asset('assets/logo.png') }}" alt="智能手机参数站" class="h-9 w-9 object-contain">
                <span>智能手机参数站</span>
            </a>

            <div class="admin-desktop-user hidden items-center gap-3">
                <a href="{{ route('home') }}" class="admin-user-chip">{{ Auth::user()->name }}</a>
                <div class="admin-theme-control" data-theme-control>
                    <button type="button" class="admin-theme-toggle" data-theme-toggle aria-label="主题设置" aria-expanded="false">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M19.43 12.98c.04-.32.07-.65.07-.98s-.02-.66-.07-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.6-.22l-2.49 1a7.4 7.4 0 0 0-1.69-.98L14.5 2.4A.49.49 0 0 0 14 2h-4a.49.49 0 0 0-.5.4l-.38 2.67c-.6.24-1.17.56-1.69.98l-2.49-1a.5.5 0 0 0-.6.22l-2 3.46a.5.5 0 0 0 .12.64l2.11 1.65c-.04.32-.08.65-.08.98s.03.66.08.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46c.14.24.43.34.69.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.67c.05.23.25.4.5.4h4c.24 0 .45-.17.5-.4l.38-2.67c.6-.24 1.17-.57 1.69-.98l2.49 1c.25.11.55.01.69-.22l2-3.46a.5.5 0 0 0-.12-.64l-2.11-1.65ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z" />
                        </svg>
                    </button>
                    <div class="admin-theme-panel" data-theme-panel hidden>
                        <div class="admin-theme-section">
                            <div class="admin-theme-label">显示模式</div>
                            <div class="admin-theme-mode-group" role="group" aria-label="显示模式">
                                <button type="button" class="admin-theme-mode-button" data-theme-mode-option="light">浅色</button>
                                <button type="button" class="admin-theme-mode-button" data-theme-mode-option="dark">深色</button>
                                <button type="button" class="admin-theme-mode-button" data-theme-mode-option="system">跟随系统</button>
                            </div>
                        </div>
                        <div class="admin-theme-section">
                            <div class="admin-theme-label">主色调</div>
                            <div class="admin-theme-color-row" role="group" aria-label="主色调">
                                <button type="button" class="admin-theme-color-button" data-theme-color-option="blue" aria-label="主色调：蓝色" title="蓝色"><span style="--theme-option-color: #2563eb"></span></button>
                                <button type="button" class="admin-theme-color-button" data-theme-color-option="emerald" aria-label="主色调：翠绿" title="翠绿"><span style="--theme-option-color: #059669"></span></button>
                                <button type="button" class="admin-theme-color-button" data-theme-color-option="violet" aria-label="主色调：紫色" title="紫色"><span style="--theme-option-color: #7c3aed"></span></button>
                                <button type="button" class="admin-theme-color-button" data-theme-color-option="rose" aria-label="主色调：玫红" title="玫红"><span style="--theme-option-color: #e11d48"></span></button>
                                <button type="button" class="admin-theme-color-button" data-theme-color-option="amber" aria-label="主色调：琥珀" title="琥珀"><span style="--theme-option-color: #d97706"></span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button
                type="button"
                @click="open = ! open"
                class="admin-mobile-toggle inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 lg:hidden"
                aria-label="切换后台导航"
            >
                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="admin-mobile-menu hidden border-t border-gray-200 bg-white lg:hidden">
        <div class="space-y-1 px-4 py-3">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">控制台</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">手机管理</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('homepage.index')" :active="request()->routeIs('homepage.*')">热门管理</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('homepage-slides.index')" :active="request()->routeIs('homepage-slides.*')">轮播图管理</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">个人资料</x-responsive-nav-link>
        </div>

        <div class="border-t border-gray-200 px-4 py-3">
            <div class="admin-mobile-account-row">
                <a href="{{ route('home') }}" class="admin-user-chip">{{ Auth::user()->name }}</a>
                <div class="admin-theme-control" data-theme-control>
                    <button type="button" class="admin-theme-toggle" data-theme-toggle aria-label="主题设置" aria-expanded="false">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M19.43 12.98c.04-.32.07-.65.07-.98s-.02-.66-.07-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.6-.22l-2.49 1a7.4 7.4 0 0 0-1.69-.98L14.5 2.4A.49.49 0 0 0 14 2h-4a.49.49 0 0 0-.5.4l-.38 2.67c-.6.24-1.17.56-1.69.98l-2.49-1a.5.5 0 0 0-.6.22l-2 3.46a.5.5 0 0 0 .12.64l2.11 1.65c-.04.32-.08.65-.08.98s.03.66.08.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46c.14.24.43.34.69.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.67c.05.23.25.4.5.4h4c.24 0 .45-.17.5-.4l.38-2.67c.6-.24 1.17-.57 1.69-.98l2.49 1c.25.11.55.01.69-.22l2-3.46a.5.5 0 0 0-.12-.64l-2.11-1.65ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z" />
                        </svg>
                    </button>
                    <div class="admin-theme-panel" data-theme-panel hidden>
                        <div class="admin-theme-section">
                            <div class="admin-theme-label">显示模式</div>
                            <div class="admin-theme-mode-group" role="group" aria-label="显示模式">
                                <button type="button" class="admin-theme-mode-button" data-theme-mode-option="light">浅色</button>
                                <button type="button" class="admin-theme-mode-button" data-theme-mode-option="dark">深色</button>
                                <button type="button" class="admin-theme-mode-button" data-theme-mode-option="system">跟随系统</button>
                            </div>
                        </div>
                        <div class="admin-theme-section">
                            <div class="admin-theme-label">主色调</div>
                            <div class="admin-theme-color-row" role="group" aria-label="主色调">
                                <button type="button" class="admin-theme-color-button" data-theme-color-option="blue" aria-label="主色调：蓝色" title="蓝色"><span style="--theme-option-color: #2563eb"></span></button>
                                <button type="button" class="admin-theme-color-button" data-theme-color-option="emerald" aria-label="主色调：翠绿" title="翠绿"><span style="--theme-option-color: #059669"></span></button>
                                <button type="button" class="admin-theme-color-button" data-theme-color-option="violet" aria-label="主色调：紫色" title="紫色"><span style="--theme-option-color: #7c3aed"></span></button>
                                <button type="button" class="admin-theme-color-button" data-theme-color-option="rose" aria-label="主色调：玫红" title="玫红"><span style="--theme-option-color: #e11d48"></span></button>
                                <button type="button" class="admin-theme-color-button" data-theme-color-option="amber" aria-label="主色调：琥珀" title="琥珀"><span style="--theme-option-color: #d97706"></span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-xs text-gray-500">{{ Auth::user()->email }}</div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="admin-button-danger w-full">退出登录</button>
            </form>
        </div>
    </div>
</nav>
