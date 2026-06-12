<x-guest-layout>
@section('title', '登录')
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('电子邮件')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('密码')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('记住我') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-4">

            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('register') }}">
                {{ __('注册') }}
            </a>

            <div class="flex items-center">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                        {{ __('忘记密码？') }}
                    </a>
                @endif

                <x-primary-button class="ms-3">
                    {{ __('登录') }}
                </x-primary-button>
            </div>
        </div>
    </form>
</x-guest-layout>
