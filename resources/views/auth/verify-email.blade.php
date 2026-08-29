<x-guest-layout>
@section('title', '验证邮箱')
    <div class="admin-panel-note">
        {{ __('注册成功。请点击刚刚发送到你邮箱里的链接完成验证；如果没有收到，可以在下面重新发送一封。') }}
    </div>

    @if (session('status') === 'verification-link-sent')
        <div class="admin-alert-success mt-4">
            {{ __('新的验证邮件已发送到你注册时填写的邮箱地址。') }}
        </div>
    @endif

    <x-input-error :messages="$errors->get('email')" class="mt-4" />

    <div class="admin-form-actions admin-form-actions-between mt-6">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-primary-button>{{ __('重新发送验证邮件') }}</x-primary-button>
        </form>

        <div class="admin-form-actions">
            <a class="admin-link" href="{{ route('profile.edit') }}">{{ __('修改邮箱') }}</a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="admin-button">{{ __('退出登录') }}</button>
            </form>
        </div>
    </div>
</x-guest-layout>
