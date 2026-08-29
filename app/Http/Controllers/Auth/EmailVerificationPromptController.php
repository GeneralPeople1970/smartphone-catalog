<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerification;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Show the code entry page for the account this session is verifying.
     *
     * A guest page: while verification is required the pending account is not
     * signed in. Without a pending account there is nothing to show — and no
     * way to name one without turning this into an address prober.
     */
    public function __invoke(Request $request, EmailVerification $verification): RedirectResponse|View
    {
        $user = $verification->pending();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail() || ! SiteSettings::emailVerificationRequired()) {
            $verification->forget();

            return redirect()->route('login')->with('status', '邮箱无需验证，直接登录即可。');
        }

        return view('auth.verify-email', [
            'email' => $user->email,
            'retryAfter' => $verification->retryAfter($user),
            'ttlMinutes' => EmailVerification::CODE_TTL_MINUTES,
        ]);
    }
}
