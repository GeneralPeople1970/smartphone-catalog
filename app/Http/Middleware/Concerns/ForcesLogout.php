<?php

namespace App\Http\Middleware\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ending a session that has become invalid mid-flight — suspended account,
 * email verification switched on. Both middlewares need the exact same three
 * steps (log out, drop the session data, hand out a fresh CSRF token), and
 * getting one of them wrong is how a "logged out" user keeps their session.
 */
trait ForcesLogout
{
    protected function forceLogout(Request $request, string $message): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => $message,
        ]);
    }
}
