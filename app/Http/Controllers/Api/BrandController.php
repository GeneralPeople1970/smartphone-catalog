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
     * Group the authoritative brand column in one query. Binary grouping on
     * MySQL keeps unrelated accented names out of recognized brand counts.
     *
     * @return array<int, array{brand: string, total: int}>
     */
    private function publishedCountsByGroup(): array
    {
        $query = Product::query()->where('status', 'published');
        $brandColumn = $query->getConnection()->getDriverName() === 'mysql' ? 'BINARY brand' : 'brand';

        return $query
            ->selectRaw($brandColumn.' as brand, count(*) as total')
            ->groupByRaw($brandColumn)
            ->get()
            ->map(fn ($row) => [
                'brand' => (string) $row->brand,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $brand
     * @param  array<int, array{brand: string, total: int}>  $groups
     */
    private function phoneCountForBrand(array $brand, array $groups): int
    {
        $total = 0;

        foreach ($groups as $group) {
            if ((PhoneCatalog::entryForInput($group['brand'])['code'] ?? null) === $brand['code']) {
                $total += $group['total'];
            }
        }

        return $total;
    }
}
