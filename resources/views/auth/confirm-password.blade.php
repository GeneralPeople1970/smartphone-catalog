<x-guest-layout>
@section('title', '确认密码')
    <p class="admin-panel-note mb-4">
        {{ __('这里是应用程序的安全区域。请在继续之前确认您的密码。') }}
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <div class="admin-field">
            <x-input-label for="password" :value="__('密码')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="admin-form-actions admin-form-actions-end">
            <x-primary-button>{{ __('确认') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
