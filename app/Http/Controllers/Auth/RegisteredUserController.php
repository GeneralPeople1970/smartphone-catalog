<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailVerification;
use App\Support\SiteSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'emailVerificationRequired' => SiteSettings::emailVerificationRequired(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, EmailVerification $verification): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $verificationRequired = SiteSettings::emailVerificationRequired();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if (! $verificationRequired) {
            // With the switch off the address is accepted as-is, so the account
            // is stamped verified up front: nothing is left pending, and the
            // Registered listener has no notification to send.
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $mailFailed = false;

        try {
            // The framework listener calls sendEmailVerificationNotification(),
            // which mails the code — and does nothing for an account that is
            // already verified.
            event(new Registered($user));
        } catch (Throwable $e) {
            // The account exists either way; a mailer outage must not lose the
            // registration. The code page offers a resend.
            report($e);
            $mailFailed = true;
        }

        if (! $verificationRequired) {
            // New accounts are plain, active users. They receive the shared backend
            // dashboard shell, while role middleware keeps management routes closed.
            Auth::login($user);

            return redirect(route('dashboard', absolute: false));
        }

        // Not signed in: while verification is required, an unverified account
        // holds no session. The session only remembers who is verifying.
        $verification->remember($user);

        $redirect = redirect(route('verification.notice', absolute: false));

        return $mailFailed
            ? $redirect->withErrors(['code' => '验证码发送失败，请点击下方按钮重新发送，或联系管理员。'])
            : $redirect->with('status', 'verification-code-sent');
    }
}
