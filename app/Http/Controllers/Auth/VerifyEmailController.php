<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    /**
     * Check the submitted code and, if it matches, mark the pending account
     * verified and sign it in.
     *
     * The session already proved the password (registration, or a login that was
     * turned away for being unverified), and the code proves the address, so
     * there is nothing left to ask for.
     */
    public function __invoke(Request $request, EmailVerification $verification): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = $verification->pending();

        if ($user === null) {
            return redirect()->route('login')->withErrors([
                'email' => '验证会话已过期，请重新登录后再验证邮箱。',
            ]);
        }

        if (! $verification->check($user, $validated['code'])) {
            return back()->withErrors([
                'code' => '验证码不正确或已过期，请重新获取。',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $verification->forget();

        // A suspended account is verified but still must not get in; the login
        // screen is where that gets explained.
        if ($user->isSuspended()) {
            return redirect()->route('login')->withErrors([
                'email' => trans('auth.suspended'),
            ]);
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
