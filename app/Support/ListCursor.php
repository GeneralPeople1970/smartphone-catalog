<?php

namespace App\Support;

/**
 * Opaque keyset-pagination cursor for the public phone list.
 *
 * Encodes the sort-key tuple of the last row of a page — (undated flag,
 * release_date, name, id) — as base64url JSON. `id` is the unique final
 * tie-breaker, so iteration is stable even when many rows share a release
 * date and name.
 */
class ListCursor
{
    /**
     * @param  array{f: int, rd: int, n: string, id: int}  $key
     */
    public static function encode(array $key): string
    {
        $json = json_encode([
            'f' => (int) $key['f'],
            'rd' => (int) $key['rd'],
            'n' => (string) $key['n'],
            'id' => (int) $key['id'],
        ]);

        return rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');
    }

    /**
     * Decode and validate a cursor. Returns null for anything malformed —
     * callers turn that into a 422, never a 500.
     *
     * @return array{f: int, rd: int, n: string, id: int}|null
     */
    public static function decode(?string $cursor): ?array
    {
        if (! is_string($cursor) || $cursor === '' || strlen($cursor) > 1024) {
            return null;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);

        if (! is_array($data)) {
            return null;
        }

        foreach (['f', 'rd', 'id'] as $intKey) {
            if (! isset($data[$intKey]) || ! is_int($data[$intKey])) {
                return null;
            }
        }

        if (! array_key_exists('n', $data) || ! is_string($data['n'])) {
            return null;
        }

        if (! in_array($data['f'], [0, 1], true) || $data['rd'] < 0 || $data['id'] < 0) {
            return null;
        }

        return ['f' => $data['f'], 'rd' => $data['rd'], 'n' => $data['n'], 'id' => $data['id']];
    }
}
