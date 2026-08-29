<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Mail a fresh code to the account this session is verifying.
     *
     * At most one mail per minute per account (App\Services\EmailVerification);
     * and a broken mailer must not turn into a 500 on the page whose whole
     * purpose is to recover from that, so the failure is logged and reported
     * back in the form.
     */
    public function store(Request $request, EmailVerification $verification): RedirectResponse
    {
        $user = $verification->pending();

        if ($user === null) {
            return redirect()->route('login')->withErrors([
                'email' => '验证会话已过期，请重新登录后再验证邮箱。',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            $verification->forget();

            return redirect()->route('login')->with('status', '邮箱已验证，直接登录即可。');
        }

        if ($seconds = $verification->retryAfter($user)) {
            return back()->withErrors([
                'code' => "验证码刚刚发送过，请 {$seconds} 秒后再试。",
            ]);
        }

        try {
            $verification->send($user);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'code' => '验证码发送失败，请稍后重试或联系管理员。',
            ]);
        }

        return back()->with('status', 'verification-code-sent');
    }
}
