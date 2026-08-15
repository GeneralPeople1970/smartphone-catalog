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
    <body class="font-sans text-gray-900 antialiased">
        <div class="admin-root flex min-h-screen flex-col items-center justify-center px-4 py-8">
            <a href="{{ route('home') }}" class="admin-brand mb-6">
                <img src="{{ asset('assets/logo.png') }}" alt="智能手机参数站" class="h-12 w-12 object-contain">
                <span>智能手机参数站</span>
            </a>

            <div class="admin-panel w-full max-w-md p-6">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
