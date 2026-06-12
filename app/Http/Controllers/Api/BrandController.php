<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\PhoneCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $fields = $this->requestedFields($request);

        $brands = collect(PhoneCatalog::brands())
            ->map(fn (array $brand) => $this->onlyFields([
                'name' => $brand['name'],
                'code' => $brand['code'],
                'displayName' => $brand['displayName'],
                'logo' => $brand['logo'],
                'path' => $brand['path'],
                'sort' => $brand['sort'],
                'phoneCount' => $this->publishedPhoneCount($brand),
            ], $fields))
            ->values();

        return response()->json($brands);
    }

    /**
     * @return array<int, string>
     */
    private function requestedFields(Request $request): array
    {
        $allowed = ['name', 'code', 'displayName', 'logo', 'path', 'sort', 'phoneCount'];
        $fields = $this->parseList($request->query('fields'));

        if ($fields === []) {
            return $allowed;
        }

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

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function onlyFields(array $item, array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $item[$field]])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $brand
     */
    private function publishedPhoneCount(array $brand): int
    {
        return Product::query()
            ->where('status', 'published')
            ->where(function (Builder $query) use ($brand) {
                $query->whereIn('brand', PhoneCatalog::resolveBrandNames($brand['code']));

                if (! empty($brand['sourceFiles'])) {
                    $query->orWhereIn('source_file', $brand['sourceFiles']);
                }
            })
            ->count();
    }
}
