<x-guest-layout>
@section('title', '验证邮箱')
    <div class="admin-panel-note">
        验证码已发送到 <span class="admin-text-strong">{{ $email }}</span>，请输入邮件里的 6 位数字完成验证。验证码 {{ $ttlMinutes }} 分钟内有效。
    </div>

    @if (session('status') === 'verification-code-sent')
        <div class="admin-alert-success mt-4">新的验证码已发送，请查收邮件。</div>
    @endif

    <form method="POST" action="{{ route('verification.verify') }}" class="mt-4 space-y-4">
        @csrf

        <div class="admin-field">
            <x-input-label for="code" :value="__('邮箱验证码')" />
            <x-text-input
                id="code"
                type="text"
                name="code"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                pattern="[0-9]{6}"
                placeholder="6 位数字"
                required
                autofocus
            />
            <x-input-error :messages="$errors->get('code')" />
        </div>

        <div class="admin-form-actions admin-form-actions-between">
            <a class="admin-link" href="{{ route('login') }}">{{ __('返回登录') }}</a>

            <x-primary-button>{{ __('完成验证') }}</x-primary-button>
        </div>
    </form>

    {{-- One mail per minute per account, so the button counts the wait down
         instead of failing on submit. Alpine only adds the countdown: the button
         is already disabled server-side, so a failed script leaves it correct
         until the page is reloaded. --}}
    <form
        method="POST"
        action="{{ route('verification.send') }}"
        class="mt-4"
        x-data="{ wait: {{ (int) $retryAfter }} }"
        x-init="setInterval(() => wait > 0 && wait--, 1000)"
    >
        @csrf

        <button type="submit" class="admin-button" x-bind:disabled="wait > 0" @disabled($retryAfter > 0)>
            {{ __('重新发送验证码') }}<template x-if="wait > 0"><span>（<span x-text="wait"></span> 秒）</span></template>
        </button>
    </form>
</x-guest-layout>
