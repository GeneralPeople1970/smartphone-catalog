<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-theme-mode="light"
    data-bs-theme="light"
    data-resolved-theme="light"
    data-primary-color="blue"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>
            @hasSection('title')
                @yield('title') - {{ config('app.name', '智能手机参数站') }}
            @else
                {{ config('app.name', '智能手机参数站') }}
            @endif
        </title>

        <link rel="icon" type="image/png" href="{{ asset('assets/logo.png') }}">

        <script>
            (() => {
                const root = document.documentElement;
                const colors = {
                    blue: { value: '#2563eb', rgb: '37, 99, 235', hover: '#1d4ed8' },
                    emerald: { value: '#059669', rgb: '5, 150, 105', hover: '#047857' },
                    violet: { value: '#7c3aed', rgb: '124, 58, 237', hover: '#6d28d9' },
                    rose: { value: '#e11d48', rgb: '225, 29, 72', hover: '#be123c' },
                    amber: { value: '#d97706', rgb: '217, 119, 6', hover: '#b45309' },
                };
                const modes = ['light', 'dark', 'system'];
                const defaults = { mode: 'light', primaryColor: 'blue' };
                let theme = defaults;

                try {
                    theme = {
                        ...defaults,
                        ...JSON.parse(localStorage.getItem('smartphone_catalog_theme') || '{}'),
                    };
                } catch (error) {
                    theme = defaults;
                }

                if (! modes.includes(theme.mode)) theme.mode = defaults.mode;
                if (! colors[theme.primaryColor]) theme.primaryColor = defaults.primaryColor;

                const media = window.matchMedia('(prefers-color-scheme: dark)');
                const palette = colors[theme.primaryColor];
                const resolved = theme.mode === 'system'
                    ? (media.matches ? 'dark' : 'light')
                    : theme.mode;

                root.dataset.themeMode = theme.mode;
                root.dataset.bsTheme = resolved;
                root.dataset.resolvedTheme = resolved;
                root.dataset.primaryColor = theme.primaryColor;
                root.style.setProperty('--bs-primary', palette.value);
                root.style.setProperty('--bs-primary-rgb', palette.rgb);
                root.style.setProperty('--bs-link-color', palette.value);
                root.style.setProperty('--bs-link-hover-color', palette.hover);
                root.style.setProperty('--app-primary', palette.value);
                root.style.setProperty('--app-primary-rgb', palette.rgb);
                root.style.setProperty('--app-primary-hover', palette.hover);
                root.style.setProperty('--ui-primary', palette.value);
                root.style.setProperty('--ui-primary-rgb', palette.rgb);
                root.style.setProperty('--ui-primary-hover', palette.hover);
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="admin-root">
            @include('layouts.navigation')

            <div class="admin-shell flex">
                @include('layouts.sidebar')

                <div class="admin-main">
                    @if (isset($header))
                        <header class="admin-header">
                            <div class="admin-container py-6">
                                {{ $header }}
                            </div>
                        </header>
                    @endif

                    <main>
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>
        <style>
            @media (min-width: 992px) {
                .admin-desktop-user {
                    display: flex !important;
                }

                .admin-mobile-toggle,
                .admin-mobile-menu {
                    display: none !important;
                }

                .admin-sidebar {
                    display: block !important;
                }

                .admin-shell {
                    min-height: calc(100vh - var(--shared-nav-height) - var(--shared-nav-menu-height));
                }
            }

            @media (max-width: 991.98px) {
                .admin-desktop-user {
                    display: none !important;
                }

                .admin-sidebar {
                    display: none !important;
                }

                .admin-shell {
                    min-height: calc(100vh - var(--shared-nav-mobile-height));
                }
            }
        </style>
    </body>
</html>
