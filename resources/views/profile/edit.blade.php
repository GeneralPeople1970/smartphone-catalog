<x-app-layout>
    @section('title', '个人资料')

    <x-slot name="header">
        <div>
            <h1 class="admin-page-title">个人资料</h1>
            <p class="admin-page-subtitle">维护后台账号信息和登录安全。</p>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container max-w-4xl space-y-6">
            <section class="admin-panel">
                <div class="admin-panel-body max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-body max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-body max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
