<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
    public function store(Request $request): RedirectResponse
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
            event(new Registered($user));
        } catch (Throwable $e) {
            // The account exists and is signed in either way; a mailer outage
            // must not lose the registration. The notice page offers a resend.
            report($e);
            $mailFailed = true;
        }

        Auth::login($user);

        if (! $verificationRequired) {
            // New accounts are plain, active users. They receive the shared backend
            // dashboard shell, while role middleware keeps management routes closed.
            return redirect(route('dashboard', absolute: false));
        }

        $redirect = redirect(route('verification.notice', absolute: false));

        return $mailFailed
            ? $redirect->withErrors(['email' => '验证邮件发送失败，请点击下方按钮重新发送，或联系管理员。'])
            : $redirect->with('status', 'verification-link-sent');
    }
}
