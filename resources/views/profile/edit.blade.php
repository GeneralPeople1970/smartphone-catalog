<x-app-layout>
    @section('title', '个人资料')

    <x-slot name="header">
        <div>
            <h1 class="admin-page-title">个人资料</h1>
            <p class="admin-page-subtitle">维护后台账号信息和登录安全。</p>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container">
            <div class="admin-form-shell-narrow space-y-6">
                <section class="admin-panel admin-panel-body">
                    @include('profile.partials.update-profile-information-form')
                </section>

                <section class="admin-panel admin-panel-body">
                    @include('profile.partials.update-password-form')
                </section>

                <section class="admin-panel admin-panel-body">
                    @include('profile.partials.delete-user-form')
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
