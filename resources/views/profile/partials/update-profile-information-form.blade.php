<section>
@section('title', '更新您的账户个人资料信息和电子邮件地址。')
    <header>
        <h2 class="admin-panel-title">{{ __('个人信息') }}</h2>
        <p class="admin-panel-note">{{ __('更新您的账户个人资料信息和电子邮件地址。') }}</p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('patch')

        <div class="admin-field">
            <x-input-label for="name" :value="__('姓名')" />
            <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div class="admin-field">
            <x-input-label for="email" :value="__('邮箱')" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="admin-form-actions">
            <x-primary-button>{{ __('保存') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
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
