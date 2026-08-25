<section>
@section('title', '更新密码')
    <header>
        <h2 class="admin-panel-title">{{ __('更新密码') }}</h2>
        <p class="admin-panel-note">{{ __('请确保您的账户使用一个长而随机的密码以保持安全。') }}</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('put')

        <div class="admin-field">
            <x-input-label for="update_password_current_password" :value="__('当前密码')" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" />
        </div>

        <div class="admin-field">
            <x-input-label for="update_password_password" :value="__('新密码')" />
            <x-text-input id="update_password_password" name="password" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" />
        </div>

        <div class="admin-field">
            <x-input-label for="update_password_password_confirmation" :value="__('确认密码')" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" />
        </div>

        <div class="admin-form-actions">
            <x-primary-button>{{ __('保存') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="admin-text-muted text-sm"
                >{{ __('已保存。') }}</p>
            @endif
        </div>
    </form>
</section>
