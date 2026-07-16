<?php

namespace App\Http\Controllers\Api\Concerns;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Shared validation for public API query parameters. Guarantees that a
 * malformed request (array where a string is expected, non-integer or
 * out-of-range page/limit, oversized values) produces the project's unified
 * 422 JSON response instead of a PHP TypeError/warning or a 500.
 */
trait ValidatesApiQuery
{
    /**
     * Hard cap on the compatibility `page` parameter. Bounds the OFFSET so a
     * huge page number can never overflow to a negative/invalid SQL offset;
     * keyset/cursor paging is the path for deep traversal.
     */
    private const MAX_PAGE = 100000;

    /**
     * Validate the incoming query against the given rule set and abort with a
     * 422 JSON body (matching the shared error contract) on failure.
     *
     * @param  array<string, mixed>  $rules
     */
    private function validateApiQuery(Request $request, array $rules): void
    {
        $validator = Validator::make($request->query(), $rules);

        if ($validator->fails()) {
            abort(response()->json([
                'message' => '请求参数无效。',
                'errors' => $validator->errors()->toArray(),
            ], 422));
        }
    }

    /**
     * Rule set covering every parameter the phone list/search endpoints accept.
     * Callers merge/override (e.g. `q` or `slug` required) as needed.
     *
     * @return array<string, mixed>
     */
    private function phoneQueryRules(): array
    {
        return [
            'brand' => ['sometimes', 'nullable', 'string', 'max:191'],
            // `q` length is intentionally not capped here: overlong keywords are
            // truncated (not rejected) downstream to preserve existing behavior.
            'q' => ['sometimes', 'nullable', 'string'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:191'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'paginate' => ['sometimes', 'nullable', 'string', 'in:page,cursor'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
            // Only enforce integer-ness here; the < 1 check and the silent
            // clamp above the cap live in requestedLimit() with its own message.
            'limit' => ['sometimes', 'nullable', 'integer'],
            // Comma-separated string OR a flat array of scalars; nested arrays
            // and object values are rejected.
            'fields' => ['sometimes', 'nullable', $this->scalarOrFlatArrayRule()],
            'ids' => ['sometimes', 'nullable', $this->scalarOrFlatArrayRule()],
            'name' => ['sometimes', 'nullable', $this->scalarOrFlatArrayRule()],
            'names' => ['sometimes', 'nullable', $this->scalarOrFlatArrayRule()],
        ];
    }

    /**
     * A rule that accepts a scalar (string/number) or a flat array whose every
     * element is a scalar. Rejects nested arrays / object-shaped input such as
     * `ids[][]=1` or `fields[a]=b`.
     */
    private function scalarOrFlatArrayRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_scalar($value)) {
                return;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (! is_scalar($item)) {
                        $fail("The {$attribute} field is malformed.");

                        return;
                    }
                }

                return;
            }

            $fail("The {$attribute} field is malformed.");
        };
    }
}
