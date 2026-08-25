<section class="space-y-4">
@section('title', '删除账户')
    <header>
        <h2 class="admin-panel-title">{{ __('删除账户') }}</h2>
        <p class="admin-panel-note">{{ __('一旦您的账户被删除，其所有资源和数据将永久删除。在删除您的账户之前，请下载您希望保留的任何数据或信息。') }}</p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('删除账户') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="admin-panel-body">
            @csrf
            @method('delete')

            <h2 class="admin-panel-title">{{ __('您确定要删除您的账户吗？') }}</h2>

            <p class="admin-panel-note">{{ __('一旦您的账户被删除，其所有资源和数据将永久删除。请输入您的密码以确认您要永久删除您的账户。') }}</p>

            <x-input-error :messages="$errors->userDeletion->get('userDeletion')" />

            <div class="admin-field mt-6">
                <x-input-label for="password" value="{{ __('密码') }}" class="sr-only" />
                <x-text-input id="password" name="password" type="password" placeholder="{{ __('密码') }}" />
                <x-input-error :messages="$errors->userDeletion->get('password')" />
            </div>

            <div class="admin-form-actions admin-form-actions-end mt-6">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('取消') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('删除账户') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
