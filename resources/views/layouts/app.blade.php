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
        {{-- Mirrors the frontend `.app-container`: shared nav, then a growing
             main column. The admin has no sidebar — the shared top bar is the
             only navigation, so front and back stay visually identical. --}}
        <div class="admin-root">
            @include('layouts.navigation')

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
    </body>
</html>
