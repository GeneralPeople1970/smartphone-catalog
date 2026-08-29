<x-guest-layout>
@section('title', '注册')
    @if ($emailVerificationRequired ?? false)
        <div class="admin-note mb-4">
            <div class="admin-note-title">需要验证邮箱</div>
            <p class="mt-1">提交后我们会给你发送一封带 6 位验证码的邮件，输入验证码才能登录。请填写可以收信的地址。</p>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div class="admin-field">
            <x-input-label for="name" :value="__('用户名')" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div class="admin-field">
            <x-input-label for="email" :value="__('电子邮件')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="admin-field">
            <x-input-label for="password" :value="__('密码')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="admin-field">
            <x-input-label for="password_confirmation" :value="__('确认密码')" />
            <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="admin-form-actions admin-form-actions-between">
            <a class="admin-link" href="{{ route('login') }}">{{ __('已经注册？') }}</a>

            <x-primary-button>{{ __('注册') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
