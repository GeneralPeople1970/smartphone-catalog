<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ForcesLogout;
use App\Support\SiteSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Email verification is an operator switch, not a compile-time decision: the
 * `verified` alias points here so the requirement can be turned on and off from
 * the admin backend without a deploy.
 *
 * Unlike the framework middleware this does not park the user on a "check your
 * inbox" page while still signed in — while the requirement is on, an
 * unverified account holds no session at all. It is signed out here and turned
 * away at login, and the code is entered as a guest. Turning the switch on
 * therefore ends the sessions of everyone still unverified, on their next
 * request, without touching anybody's verified state.
 *
 * Owners are exempt through User::hasVerifiedEmail().
 */
class EnsureEmailIsVerified
{
    use ForcesLogout;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // The setting is only read for accounts that would be affected, so a
        // verified session costs no extra query.
        if ($user !== null && ! $user->hasVerifiedEmail() && SiteSettings::emailVerificationRequired()) {
            return $this->forceLogout($request, '站点已开启邮箱验证，请重新登录并完成邮箱验证。');
        }

        return $next($request);
    }
}
