<x-guest-layout>
@section('title', '忘记密码')
    <p class="admin-panel-note mb-4">
        {{ __('忘记密码了吗？没关系。只需告诉我们您的电子邮件地址，我们就会通过电子邮件向您发送一个密码重置链接，让您可以选择一个新密码。') }}
    </p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div class="admin-field">
            <x-input-label for="email" :value="__('电子邮件')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="admin-form-actions admin-form-actions-between">
            <a class="admin-link" href="{{ route('login') }}">{{ __('返回登录') }}</a>

            <x-primary-button>{{ __('发送密码重置链接') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
