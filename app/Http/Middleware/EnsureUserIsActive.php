<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ForcesLogout;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    use ForcesLogout;

    /**
     * Block suspended accounts: if an authenticated user has been suspended
     * (including mid-session, after they were already logged in), log them out
     * immediately and send them back to the login screen.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isSuspended()) {
            return $this->forceLogout($request, trans('auth.suspended'));
        }

        return $next($request);
    }
}
