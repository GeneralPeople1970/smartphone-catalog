<x-app-layout>
    @section('title', '站点设置')

    <x-slot name="header">
        <div>
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

                        <div class="admin-note">
                            <div class="admin-note-title">开启后的行为</div>
                            <ul class="mt-2 list-inside list-disc space-y-1">
                                <li>新注册账号会收到一封验证邮件，未点击链接前只能访问「个人资料」，控制台与 /admin 会被拦回验证提示页。</li>
                                <li>验证提示页可以重新发送邮件（每分钟最多 6 次）。</li>
                                <li>开启只对之后的注册生效：现有账号会被标记为已验证，避免所有人（包括你自己）被同时锁在外面。</li>
                                <li>关闭后未验证的账号立刻恢复正常访问，已有的验证链接仍然有效。</li>
                            </ul>
                        </div>

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
                                    当前 MAIL_MAILER 是 <code>{{ $mailer }}</code>，验证邮件不会真正投递（只写日志或直接丢弃）。开启前请在 .env 里配置可用的 SMTP。
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
