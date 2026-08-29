<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a fresh verification link to the signed-in user.
     *
     * A broken mailer must not turn into a 500 on a page whose whole purpose is
     * to recover from that: the failure is logged and reported back in the form.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'email' => '验证邮件发送失败，请稍后重试或联系管理员。',
            ]);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
