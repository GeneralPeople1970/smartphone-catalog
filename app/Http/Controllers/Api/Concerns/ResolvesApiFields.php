<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait ResolvesApiFields
{
    /**
     * Resolve the requested `fields` query parameter against a whitelist,
     * applying optional aliases. Aborts with the shared 422 contract when an
     * unsupported field is requested.
     *
     * @param  array<int, string>  $default  fields returned when none requested
     * @param  array<string, string>  $aliases  incoming field name => canonical name
     * @param  array<int, string>|null  $allowed  whitelist (defaults to $default)
     * @return array<int, string>
     */
    private function requestedFields(Request $request, array $default, array $aliases = [], ?array $allowed = null): array
    {
        $fields = $this->parseList($request->query('fields'));

        if ($fields === []) {
            return $default;
        }

        if ($aliases !== []) {
            $fields = collect($fields)
                ->map(fn (string $field) => $aliases[$field] ?? $field)
                ->unique()
                ->values()
                ->all();
        }

        $allowed ??= $default;
        $invalid = array_values(array_diff($fields, $allowed));

        if ($invalid !== []) {
            abort(response()->json([
                'message' => '不支持的字段。',
                'invalidFields' => $invalid,
                'allowedFields' => $allowed,
            ], 422));
        }

        return $fields;
    }

    /**
     * Project an associative value map down to the requested fields.
     *
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function onlyFields(array $values, array $fields, mixed $default = ''): array
    {
        return collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $values[$field] ?? $default])
            ->all();
    }

    /**
     * Normalize a raw price string into an int (when purely numeric), the
     * original string, or null when empty.
     */
    private function price(mixed $value): int|string|null
    {
        $price = trim((string) $value);

        if ($price === '') {
            return null;
        }

        return ctype_digit($price) ? (int) $price : $price;
    }

    /**
     * Split a comma-separated (or array) query value into a clean unique list.
     *
     * @return array<int, string>
     */
    private function parseList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return collect($items)
            ->flatMap(fn ($item) => explode(',', (string) $item))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
