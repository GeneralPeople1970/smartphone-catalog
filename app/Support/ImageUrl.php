<?php

namespace App\Support;

class ImageUrl
{
    public static function resolve(?string $url): string
    {
        $url = trim((string) $url);
        $placeholder = asset('assets/logo.png');
        if ($url === '' || str_contains($url, '\\') || preg_match('/[\x00-\x20\x7F]/', $url)) {
            return $placeholder;
        }
        if (str_starts_with($url, '/')) {
            return SafeUrl::passes($url) ? $url : $placeholder;
        }
        if (! preg_match('/^[a-z][a-z\d+\-.]*:/i', $url)) {
            return asset(ltrim($url, '/'));
        }
        if (! SafeUrl::passes($url)) {
            return $placeholder;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $appScheme = strtolower((string) parse_url(config('app.url'), PHP_URL_SCHEME));

        return $appScheme === 'https' && $scheme !== 'https' ? $placeholder : $url;
    }

    public static function managedPath(?string $url): ?string
    {
        $url = trim((string) $url);
        if (str_contains($url, '\\') || preg_match('/[\x00-\x20\x7F]/', $url)) {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $url)) {
            if (! self::sameOrigin($url, (string) config('app.url'))) {
                return null;
            }
        } elseif (str_starts_with($url, '//') || preg_match('/^[a-z][a-z\d+\-.]*:/i', $url)) {
            return null;
        }
        $path = '/'.ltrim(rawurldecode((string) parse_url($url, PHP_URL_PATH)), '/');

        return preg_match('#^/storage/homepage/[a-z0-9_.-]+\.(?:jpg|jpeg|png|webp|gif)$#iD', $path) === 1 ? $path : null;
    }

    private static function sameOrigin(string $url, string $baseUrl): bool
    {
        $parts = parse_url($url);
        $base = parse_url($baseUrl);
        if ($parts === false || $base === false) {
            return false;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $baseScheme = strtolower($base['scheme'] ?? '');

        return $scheme === $baseScheme
            && strcasecmp($parts['host'] ?? '', $base['host'] ?? '') === 0
            && ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)) === ($base['port'] ?? ($baseScheme === 'https' ? 443 : 80));
    }
}
