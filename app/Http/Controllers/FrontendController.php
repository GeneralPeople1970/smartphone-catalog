<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Js;

class FrontendController extends Controller
{
    /**
     * Serve the Vue SPA entry with the current auth state injected.
     *
     * Kept as an invokable controller rather than a route closure so the home
     * and SPA-fallback routes can be serialized by `php artisan route:cache`.
     */
    public function __invoke(Request $request)
    {
        $indexPath = public_path('frontend/index.html');

        if (! file_exists($indexPath) && app()->environment('testing')) {
            $indexPath = base_path('frontend/index.html');
        }

        if (! file_exists($indexPath)) {
            abort(500, 'The Vue entry file public/frontend/index.html was not found.');
        }

        $html = Cache::remember(
            'frontend.index.'.filemtime($indexPath),
            now()->addDay(),
            static fn (): string => (string) file_get_contents($indexPath)
        );

        $user = $request->user();
        $authPayload = [
            'authenticated' => $user !== null,
            'user' => $user ? [
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
        ];
        $authScript = '<script>window.__SMARTPHONE_CATALOG_AUTH__ = '.Js::from($authPayload).';</script>';

        if (str_contains($html, '</head>')) {
            $html = str_replace('</head>', $authScript.'</head>', $html);
        } else {
            $html = $authScript.$html;
        }

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
