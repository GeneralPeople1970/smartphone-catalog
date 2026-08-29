<?php

namespace App\Http\Middleware;

use App\Support\SiteSettings;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as BaseMiddleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Email verification is an operator switch, not a compile-time decision: the
 * `verified` alias points here so the requirement can be turned on and off from
 * the admin backend without a deploy.
 *
 * `User` always implements MustVerifyEmail (so the verification routes and the
 * notification keep working); this middleware is the only place that decides
 * whether an unverified account is actually held back.
 */
class EnsureEmailIsVerified extends BaseMiddleware
{
    /**
     * @param  Request  $request
     * @param  string|null  $redirectToRoute
     * @return Response|RedirectResponse|null
     */
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if (! SiteSettings::emailVerificationRequired()) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
