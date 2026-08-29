<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\EmailVerification;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, EmailVerification $verification): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        if (! $user->hasVerifiedEmail() && SiteSettings::emailVerificationRequired()) {
            return $this->sendToVerification($request, $verification);
        }

        $request->session()->regenerate();

        // Every active account gets the same backend shell. Role checks still
        // protect every catalog and user-management route under /admin.
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * The password was right but the address is unconfirmed: hand the account
     * back out of the session and send it to the code page. Nothing about the
     * account changes — it simply cannot hold a session until it verifies.
     */
    private function sendToVerification(Request $request, EmailVerification $verification): RedirectResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        $request->session()->regenerate();

        $verification->remember($user);

        $redirect = redirect()->route('verification.notice');

        try {
            // False means one went out less than a minute ago, so it is still
            // valid and the page will say so rather than claim a new one.
            return $verification->send($user)
                ? $redirect->with('status', 'verification-code-sent')
                : $redirect;
        } catch (Throwable $e) {
            report($e);

            return $redirect->withErrors([
                'code' => '验证码发送失败，请点击下方按钮重新发送，或联系管理员。',
            ]);
        }
    }
}
