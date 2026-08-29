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
            'unverifiedCount' => User::query()->whereNull('email_verified_at')->count(),
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
            // Accounts created while the switch was off were never asked to
            // confirm anything. Leaving them unverified would lock every one of
            // them out the moment the switch flips — including the admin doing
            // the flipping. Turning verification on therefore applies to new
            // registrations only.
            $grandfathered = User::query()->whereNull('email_verified_at')->update([
                'email_verified_at' => now(),
            ]);

            if ($grandfathered > 0) {
                $message .= " 已有 {$grandfathered} 个旧账号被视为已验证，新规则只对之后的注册生效。";
            }
        }

        return redirect()->route('settings.edit')->with('status', $message);
    }
}
