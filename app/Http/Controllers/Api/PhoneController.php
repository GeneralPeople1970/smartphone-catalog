<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiFields;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\PhoneCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PhoneController extends Controller
{
    use ResolvesApiFields;

    private const LIST_FIELDS = [
        'id',
        'phonename',
        'company',
        'companyCode',
        'socname',
        'price',
        'battery',
        'imgurl',
    ];

    private const SPEC_FIELDS = [
        'screenm',
        'charge',
        'storeage',
        'weight',
        'feature',
        'saledate',
        'official',
        'cpu',
        'gpu',
        'ramfadsf',
        'romagbcz',
        'wifi',
        'bluetooth',
        'screencolor',
        'location',
        'osui',
        'material',
        'sensor',
    ];

    private const EXTRA_FIELDS = [
        'slug',
        'brandLogo',
        'displayPrice',
    ];

    private const FIELD_ALIASES = [
        'name' => 'phonename',
        'model' => 'phonename',
        'phoneName' => 'phonename',
        'brand' => 'company',
        'brandCode' => 'companyCode',
        'processor' => 'socname',
        'soc' => 'socname',
        'image' => 'imgurl',
        'imageUrl' => 'imgurl',
        'storage' => 'storeage',
        'releaseDate' => 'saledate',
    ];

    public function index(Request $request): JsonResponse
    {
        $fields = $this->requestedFields($request, self::LIST_FIELDS, self::FIELD_ALIASES, $this->allowedFields());
        $limit = $this->requestedLimit($request);

        $query = Product::query()
            ->where('status', 'published')
            ->when($request->filled('brand'), function (Builder $query) use ($request) {
                $this->applyBrandFilter($query, $request->query('brand'));
            })
            ->when($request->filled('ids'), function (Builder $query) use ($request) {
                $ids = collect($this->parseList($request->query('ids')))
                    ->map(fn (string $id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->values()
                    ->all();

                if ($ids !== []) {
                    $query->whereIn('id', $ids);
                }
            })
            ->when($request->filled('name') || $request->filled('names'), function (Builder $query) use ($request) {
                $names = array_values(array_unique(array_merge(
                    $this->parseList($request->query('name')),
                    $this->parseList($request->query('names')),
                )));

                if ($names !== []) {
                    $query->where(function (Builder $query) use ($names) {
                        foreach ($names as $name) {
                            $query->orWhere('name', $name);
                        }
                    });
                }
            })
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $query->search((string) $request->query('q'));
            })
            ->orderBy('id');

        $phones = $this->sortPhoneList($query->get($this->selectColumns($fields)));

        if ($limit !== null) {
            $phones = $phones->take($limit);
        }

        return response()->json(
            $phones
                ->map(fn (Product $product) => $this->toItem($product, $fields))
                ->values()
        );
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'max:191'],
        ]);

        if (! $request->filled('fields')) {
            $request->query->set('fields', 'id,phonename,company,companyCode,socname,price,displayPrice,battery,imgurl,slug,brandLogo');
        }

        if (! $request->filled('limit')) {
            $request->query->set('limit', 20);
        }

        return $this->index($request);
    }

    public function brandSearch(Request $request, string $brand): JsonResponse
    {
        $request->query->set('brand', $brand);

        return $this->search($request);
    }

    public function show(Request $request, Product $phone): JsonResponse
    {
        abort_unless($phone->status === 'published', 404);

        return response()->json($this->toItem(
            $phone,
            $this->requestedFields($request, array_merge(self::LIST_FIELDS, self::SPEC_FIELDS), self::FIELD_ALIASES, $this->allowedFields())
        ));
    }

    public function detail(Request $request): JsonResponse
    {
        $request->validate([
            'brand' => ['nullable', 'string'],
            'slug' => ['required', 'string'],
        ]);

        $slug = $this->normalizeSlug($request->query('slug'));

        $products = Product::query()
            ->where('status', 'published')
            ->when($request->filled('brand'), function (Builder $query) use ($request) {
                $this->applyBrandFilter($query, $request->query('brand'));
            })
            ->get();

        $product = $products->first(fn (Product $product) => $this->normalizeSlug($product->slug ?: $product->name) === $slug);

        abort_if(! $product, 404);

        return response()->json($this->toItem(
            $product,
            $this->requestedFields($request, array_merge(self::LIST_FIELDS, self::SPEC_FIELDS), self::FIELD_ALIASES, $this->allowedFields())
        ));
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function toItem(Product $product, array $fields): array
    {
        return $this->onlyFields($this->fieldValues($product), $fields);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldValues(Product $product): array
    {
        $brand = PhoneCatalog::entryForProduct($product->brand, $product->source_file);
        $values = [
            'id' => $product->id,
            'phonename' => $product->name,
            'company' => $brand['displayName'] ?? $product->brand,
            'companyCode' => $brand['code'] ?? PhoneCatalog::codeForBrand($product->brand),
            'socname' => $product->soc_name,
            'price' => $this->price($product->price),
            'displayPrice' => $product->display_price,
            'battery' => $product->battery_capacity,
            'imgurl' => $product->image_url,
            'slug' => $product->slug,
            'brandLogo' => $brand['logo'] ?? null,
        ];

        foreach (self::SPEC_FIELDS as $field) {
            $values[$field] = $this->spec($product, $field);
        }

        return $values;
    }

    private function spec(Product $product, string $key): mixed
    {
        return data_get($product->specs, $key, '');
    }

    private function sortPhoneList($products)
    {
        return $products
            ->map(fn (Product $product) => [
                'product' => $product,
                'date' => $this->releaseDate($product),
                'series' => $this->seriesKey($product),
                'variant' => $this->variantRank($product->name),
                'name' => (string) $product->name,
            ])
            ->sort(function (array $left, array $right) {
                if (($left['date'] > 0) !== ($right['date'] > 0)) {
                    return $left['date'] > 0 ? -1 : 1;
                }

                if ($left['date'] === 0 && $right['date'] === 0) {
                    return strnatcasecmp($left['name'], $right['name']);
                }

                if ($left['date'] !== $right['date']) {
                    return $right['date'] <=> $left['date'];
                }

                if ($left['series'] !== $right['series']) {
                    return strnatcasecmp($left['series'], $right['series']);
                }

                if ($left['variant'] !== $right['variant']) {
                    return $left['variant'] <=> $right['variant'];
                }

                return strnatcasecmp($left['name'], $right['name']);
            })
            ->map(fn (array $item) => $item['product'])
            ->values();
    }

    /**
     * Columns required to build the response. The heavy `specs` JSON is only
     * loaded when a spec field is requested; list views skip it entirely.
     *
     * @param  array<int, string>  $fields
     * @return array<int, string>
     */
    private function selectColumns(array $fields): array
    {
        $columns = ['id', 'name', 'brand', 'source_file', 'soc_name', 'price', 'battery_capacity', 'image_url', 'slug', 'release_date'];

        if (array_intersect($fields, self::SPEC_FIELDS) !== []) {
            $columns[] = 'specs';
        }

        return $columns;
    }

    private function releaseDate(Product $product): int
    {
        return (int) ($product->release_date ?? 0);
    }

    private function seriesKey(Product $product): string
    {
        $name = mb_strtolower($product->name);
        $name = preg_replace('/[（(].*?[）)]/u', '', $name) ?? $name;
        $name = preg_replace('/\b(5g|4g|wifi|lte)\b/iu', '', $name) ?? $name;
        $name = preg_replace('/\b(pro max|promax|pro\+|pro|max|plus|ultra|mini|se|fe|air)\b/iu', '', $name) ?? $name;
        $name = preg_replace('/(至尊版|至臻版|典藏版|冠军版|探索版|大师版|活力版|青春版|竞速版|标准版|优享版|高配版|低配版|屏幕指纹版|透明探索版)/u', '', $name) ?? $name;
        $name = preg_replace('/[^\p{Han}a-z0-9]+/iu', '', $name) ?? $name;

        return $name !== '' ? $name : mb_strtolower($product->brand.'-'.$product->name);
    }

    private function variantRank(string $name): int
    {
        $name = mb_strtolower($name);

        return match (true) {
            str_contains($name, 'ultra'), str_contains($name, '至尊'), str_contains($name, '至臻') => 10,
            str_contains($name, 'pro max'), str_contains($name, 'promax') => 20,
            str_contains($name, 'pro+') => 25,
            str_contains($name, 'pro') => 30,
            str_contains($name, 'plus') => 40,
            str_contains($name, 'max') => 50,
            str_contains($name, 'mini'), str_contains($name, 'se'), str_contains($name, '青春'), str_contains($name, '活力') => 70,
            default => 60,
        };
    }

    private function normalizeSlug(string $value): string
    {
        return (string) Str::of(rawurldecode($value))
            ->lower()
            ->replaceMatches('/[\s\/]+/u', '-')
            ->replaceMatches('/-+/u', '-')
            ->trim('-');
    }

    private function applyBrandFilter(Builder $query, ?string $brand): void
    {
        $entry = PhoneCatalog::entryForInput($brand);

        if ($entry && ! empty($entry['sourceFiles'])) {
            $query->where(function (Builder $query) use ($brand, $entry) {
                $query->whereIn('brand', PhoneCatalog::resolveBrandNames($brand))
                    ->orWhereIn('source_file', $entry['sourceFiles']);
            });

            return;
        }

        $query->whereIn('brand', PhoneCatalog::resolveBrandNames($brand));
    }

    /**
     * @return array<int, string>
     */
    private function allowedFields(): array
    {
        return array_values(array_unique(array_merge(self::LIST_FIELDS, self::SPEC_FIELDS, self::EXTRA_FIELDS)));
    }

    private function requestedLimit(Request $request): ?int
    {
        if (! $request->filled('limit')) {
            return null;
        }

        $limit = (int) $request->query('limit');

        if ($limit < 1) {
            abort(response()->json([
                'message' => 'limit 至少为 1。',
            ], 422));
        }

        return min($limit, 500);
    }
}
