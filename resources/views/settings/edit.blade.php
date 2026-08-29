<x-app-layout>
    @section('title', '站点设置')

    <x-slot name="header">
        <div class="admin-form-shell-narrow">
            <h1 class="admin-page-title">站点设置</h1>
            <p class="admin-page-subtitle">运行时开关，保存后立即生效，不需要重新部署。</p>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container">
            <div class="admin-form-shell-narrow space-y-6">
                @if (session('status'))
                    <div class="admin-alert-success">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('settings.update') }}" class="admin-panel">
                    @csrf
                    @method('PUT')

                    <div class="admin-panel-body space-y-5">
                        <div>
                            <div class="admin-panel-title">注册与登录</div>
                            <p class="admin-panel-note">控制新账号是否必须先确认邮箱才能进入后台。</p>
                        </div>

                        {{-- The hidden field carries the "off" value: an unchecked
                             box sends nothing at all. --}}
                        <input type="hidden" name="registration_email_verification" value="0">

                        <label class="admin-checkbox-field">
                            <input
                                type="checkbox"
                                name="registration_email_verification"
                                value="1"
                                class="admin-checkbox"
                                @checked($emailVerificationRequired)
                            >
                            注册时验证邮箱
                        </label>

                        <p class="admin-hint">
                            开启后注册需要输入邮件里的 6 位验证码，未验证的账号会被退出登录；所有者不受此限制。
                        </p>

                        @unless ($actorVerified)
                            <p class="admin-warning-text">
                                你的邮箱尚未验证。开启后你会被退出登录，需要用验证码重新登录。
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
                                    当前 MAIL_MAILER 是 <code>{{ $mailer }}</code>，验证码邮件不会真正投递（只写日志或直接丢弃）。开启前请在 .env 里配置可用的 SMTP。
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
