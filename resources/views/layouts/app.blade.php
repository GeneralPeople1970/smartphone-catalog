<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-bs-theme="light"
    data-resolved-theme="light"
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
                const media = window.matchMedia('(prefers-color-scheme: dark)');

                const applySystemTheme = () => {
                    const resolved = media.matches ? 'dark' : 'light';

                    root.dataset.bsTheme = resolved;
                    root.dataset.resolvedTheme = resolved;
                };

                applySystemTheme();

                if (media.addEventListener) {
                    media.addEventListener('change', applySystemTheme);
                } else {
                    media.addListener(applySystemTheme);
                }
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
