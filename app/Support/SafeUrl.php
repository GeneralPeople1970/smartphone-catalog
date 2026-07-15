<?php

namespace App\Support;

class SafeUrl
{
    /**
     * Whether a URL is safe to render into an href/src. Only a site-relative
     * path (starting with a single "/") or an absolute http/https URL with a
     * host is allowed. Rejects javascript:/data:/vbscript: and any other
     * scheme, protocol-relative "//host" and backslash "/\" tricks, and any
     * control character / whitespace used to obfuscate a scheme.
     */
    public static function passes(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
            return false;
        }

        if (str_starts_with($value, '/')) {
            // Reject protocol-relative "//host" and "/\host".
            return preg_match('#^/[\\\\/]#', $value) !== 1;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        if (! is_string($scheme) || ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '';
    }

    /**
     * Return the URL when it is safe, otherwise null.
     */
    public static function sanitize(?string $value): ?string
    {
        return self::passes($value) ? $value : null;
    }
}
