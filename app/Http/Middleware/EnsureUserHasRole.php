<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Ensure the authenticated user holds one of the given roles.
     *
     * Usage: ->middleware('role:editor,admin,owner'). This is a coarse route
     * gate; fine-grained write rules are still enforced by policies.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $allowed = array_map(
            static fn (string $role): UserRole => UserRole::from($role),
            $roles
        );

        if (! $user->hasRole(...$allowed)) {
            abort(403);
        }

        return $next($request);
    }
}
