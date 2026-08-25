<x-guest-layout>
@section('title', '登录')
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div class="admin-field">
            <x-input-label for="email" :value="__('电子邮件')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="admin-field">
            <x-input-label for="password" :value="__('密码')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <label class="admin-checkbox-field">
            <input id="remember_me" type="checkbox" name="remember" class="admin-checkbox">
            {{ __('记住我') }}
        </label>

        <div class="admin-form-actions admin-form-actions-between">
            <a class="admin-link" href="{{ route('register') }}">{{ __('注册') }}</a>

            <div class="admin-form-actions">
                @if (Route::has('password.request'))
                    <a class="admin-link" href="{{ route('password.request') }}">{{ __('忘记密码？') }}</a>
                @endif

                <x-primary-button>{{ __('登录') }}</x-primary-button>
            </div>
        </div>
    </form>
</x-guest-layout>
