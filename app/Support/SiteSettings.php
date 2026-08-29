<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Typed accessors for the `site_settings` table.
 *
 * Deliberately uncached: each read is one indexed lookup on a table with a
 * handful of rows, and these values gate authentication behaviour — a stale
 * cache entry would keep letting unverified accounts through after an operator
 * has closed that door.
 */
class SiteSettings
{
    /**
     * Whether a new registration must confirm its email address before the
     * account can reach the dashboard.
     */
    public const REGISTRATION_EMAIL_VERIFICATION = 'registration_email_verification';

    public static function emailVerificationRequired(): bool
    {
        return self::bool(self::REGISTRATION_EMAIL_VERIFICATION);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }

    public static function get(string $key): ?string
    {
        $value = SiteSetting::query()->where('key', $key)->value('value');

        return $value === null ? null : (string) $value;
    }

    public static function putBool(string $key, bool $value): void
    {
        self::put($key, $value ? '1' : '0');
    }

    public static function put(string $key, string $value): void
    {
        SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
