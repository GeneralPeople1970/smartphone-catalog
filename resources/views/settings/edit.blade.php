<x-app-layout>
    @section('title', '站点设置')

    <x-slot name="header">
        <div class="admin-form-shell-narrow">
            <h1 class="admin-page-title">站点设置</h1>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container">
            <div class="admin-form-shell-narrow space-y-6">
                <x-admin-feedback />

                <form method="POST" action="{{ route('settings.update') }}" class="admin-panel">
                    @csrf
                    @method('PUT')

                    <div class="admin-panel-body space-y-5">
                        <div>
                            <div class="admin-panel-title">注册与登录</div>
                        </div>

                        <x-admin-checkbox name="registration_email_verification" :checked="old('registration_email_verification', $emailVerificationRequired)">注册时验证邮箱</x-admin-checkbox>

                        @unless ($actorVerified)
                            <p class="admin-warning-text">
                                你的邮箱尚未验证，开启后需重新登录验证。
                            </p>
                        @endunless

                        <div class="admin-note">
                            <div class="admin-note-title">当前邮件配置</div>
                            <dl class="admin-meta-list mt-2">
                                <div class="admin-meta-row">
                                    <dt>MAIL_MAILER</dt>
                                    <dd>{{ $mailer ?: '未配置' }}</dd>
                                </div>
                                <div class="admin-meta-row">
                                    <dt>发件地址</dt>
                                    <dd>{{ $mailFromAddress ?: '未配置' }}</dd>
                                </div>
                                <div class="admin-meta-row">
                                    <dt>未验证账号</dt>
                                    <dd>{{ $unverifiedCount }}</dd>
                                </div>
                            </dl>

                            @if (in_array($mailer, ['log', 'array', 'null'], true))
                                <p class="admin-warning-text mt-2">
                                    邮件无法投递，请先配置 SMTP。
                                </p>
                            @endif
                        </div>

                        <div class="admin-form-actions admin-form-actions-end">
                            <button type="submit" class="admin-button-primary">保存设置</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
