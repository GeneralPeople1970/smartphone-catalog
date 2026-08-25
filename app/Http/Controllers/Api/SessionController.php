<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Report whether the current browser session is authenticated.
     *
     * An invokable controller rather than a route closure so `route:cache` can
     * serialize the /api/me route.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'authenticated' => $user !== null,
            // Same-origin session endpoint, so the token is safe to hand back and
            // lets the SPA keep its logout form valid after a client-side route
            // change (the bootstrap payload is only injected on a full load).
            'csrfToken' => $user !== null ? csrf_token() : null,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // Capability flag (not the raw role): lets the SPA decide
                // whether the username links to /dashboard or /profile.
                // Server-side middleware/Policies remain the real gate.
                'canAccessAdmin' => $user->canAccessAdmin(),
            ] : null,
        ]);
    }
}
