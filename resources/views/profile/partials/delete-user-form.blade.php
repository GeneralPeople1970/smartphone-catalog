<section class="space-y-6">
@section('title', '删除账户')
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('删除账户') }} <!-- Delete Account -->
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('一旦您的账户被删除，其所有资源和数据将永久删除。在删除您的账户之前，请下载您希望保留的任何数据或信息。') }} <!-- Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain. -->
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('删除账户') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900">
                {{ __('您确定要删除您的账户吗？') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('一旦您的账户被删除，其所有资源和数据将永久删除。请输入您的密码以确认您要永久删除您的账户。') }}
            </p>

            <x-input-error :messages="$errors->userDeletion->get('userDeletion')" class="mt-4" />

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('密码') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('密码') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('取消') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('删除账户') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
