<x-guest-layout>
@section('title', '忘记密码')
    <div class="mb-4 text-sm text-gray-600">
        {{ __('忘记密码了吗？没关系。只需告诉我们您的电子邮件地址，我们就会通过电子邮件向您发送一个密码重置链接，让您可以选择一个新密码。') }}
    </div>

    <!-- 会话状态信息 -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- 电子邮件地址 -->
        <div>
            <x-input-label for="email" :value="__('电子邮件')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- 修改这个flex容器以包含返回按钮 --}}
        <div class="flex items-center justify-between mt-4">
            {{-- 返回按钮 --}}
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
               href="{{ route('login') }}">
                {{ __('返回登录') }}
            </a>

            <x-primary-button>
                {{ __('发送密码重置链接') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
