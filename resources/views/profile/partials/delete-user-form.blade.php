<section class="space-y-4">
@section('title', '删除账户')
    <header>
        <h2 class="admin-panel-title">{{ __('删除账户') }}</h2>
        <p class="admin-panel-note">{{ __('删除后无法恢复。') }}</p>
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

            <p class="admin-panel-note">{{ __('请输入密码确认，删除后无法恢复。') }}</p>

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
