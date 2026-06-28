<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiFields;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\PhoneCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    use ResolvesApiFields;

    private const FIELDS = ['name', 'code', 'displayName', 'logo', 'path', 'sort', 'phoneCount'];

    public function index(Request $request): JsonResponse
    {
        $fields = $this->requestedFields($request, self::FIELDS);
        $groups = $this->publishedCountsByGroup();

        $brands = collect(PhoneCatalog::brands())
            ->map(fn (array $brand) => $this->onlyFields([
                'name' => $brand['name'],
                'code' => $brand['code'],
                'displayName' => $brand['displayName'],
                'logo' => $brand['logo'],
                'path' => $brand['path'],
                'sort' => $brand['sort'],
                'phoneCount' => $this->phoneCountForBrand($brand, $groups),
            ], $fields))
            ->values();

        return response()->json($brands);
    }

    /**
     * One pass over published products grouped by (brand, source_file). Each
     * product falls into exactly one group, so summing matched groups per brand
     * counts every row once — no double counting across the brand/source match.
     *
     * @return array<int, array{brand: string, source_file: ?string, total: int}>
     */
    private function publishedCountsByGroup(): array
    {
        return Product::query()
            ->where('status', 'published')
            ->selectRaw('brand, source_file, count(*) as total')
            ->groupBy('brand', 'source_file')
            ->get()
            ->map(fn ($row) => [
                'brand' => (string) $row->brand,
                'source_file' => $row->source_file,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $brand
     * @param  array<int, array{brand: string, source_file: ?string, total: int}>  $groups
     */
    private function phoneCountForBrand(array $brand, array $groups): int
    {
        $names = PhoneCatalog::resolveBrandNames($brand['code']);
        $sourceFiles = $brand['sourceFiles'] ?? [];

        $total = 0;

        foreach ($groups as $group) {
            $matchesBrand = in_array($group['brand'], $names, true);
            $matchesSource = $group['source_file'] !== null && in_array($group['source_file'], $sourceFiles, true);

            if ($matchesBrand || $matchesSource) {
                $total += $group['total'];
            }
        }

        return $total;
    }
}
