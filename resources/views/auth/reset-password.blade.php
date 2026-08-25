<x-guest-layout>
@section('title', '重置密码')
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="admin-field">
            <x-input-label for="email" :value="__('电子邮件')" />
            <x-text-input id="email" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
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

        <div class="admin-form-actions admin-form-actions-end">
            <x-primary-button>{{ __('重置密码') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
