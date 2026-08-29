<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Runtime site configuration. Reachable by admins and owners only (enforced by
 * the `role:admin,owner` middleware on the routes).
 */
class SiteSettingController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', [
            'emailVerificationRequired' => SiteSettings::emailVerificationRequired(),
            'mailer' => (string) config('mail.default'),
            'mailFromAddress' => (string) config('mail.from.address'),
            'unverifiedCount' => User::query()->unverified()->count(),
            // Turning the switch on signs out every unverified account, and the
            // operator may well be one of them.
            'actorVerified' => $request->user()->hasVerifiedEmail(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'registration_email_verification' => ['nullable', 'boolean'],
        ]);

        $enabled = (bool) ($data['registration_email_verification'] ?? false);
        $wasEnabled = SiteSettings::emailVerificationRequired();

        SiteSettings::putBool(SiteSettings::REGISTRATION_EMAIL_VERIFICATION, $enabled);

        $message = $enabled ? '已开启注册邮箱验证。' : '已关闭注册邮箱验证。';

        if ($enabled && ! $wasEnabled) {
            // Nobody's verified state is touched: unverified accounts stay
            // unverified and simply lose their session on the next request.
            $pending = User::query()->unverified()->count();

            if ($pending > 0) {
                $message .= " {$pending} 个未验证账号会被退出登录，需要重新登录并输入邮箱验证码。";
            }
        }

        return redirect()->route('settings.edit')->with('status', $message);
    }
}
