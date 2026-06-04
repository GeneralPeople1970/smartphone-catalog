<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <div class="admin-shell flex min-h-[calc(100vh-4rem)]">
                @include('layouts.sidebar')

                <div class="min-w-0 flex-1">
                    @if (isset($header))
                        <header class="border-b border-gray-200 bg-white">
                            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
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
            .admin-top-nav {
                display: block;
            }

            @media (min-width: 1024px) {
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
                    min-height: calc(100vh - 4rem);
                }
            }

            @media (max-width: 1023.98px) {
                .admin-desktop-user {
                    display: none !important;
                }

                .admin-sidebar {
                    display: none !important;
                }

                .admin-shell {
                    min-height: calc(100vh - 4rem);
                }
            }
        </style>
    </body>
</html>
