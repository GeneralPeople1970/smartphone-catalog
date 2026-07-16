<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiFields;
use App\Http\Controllers\Api\Concerns\ValidatesApiQuery;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ListCursor;
use App\Support\PhoneCatalog;
use App\Support\SafeUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhoneController extends Controller
{
    use ResolvesApiFields;
    use ValidatesApiQuery;

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

    private const MAX_LIST_ITEMS = 100;

    private const DEFAULT_LIMIT = 500;

    private const MAX_LIMIT = 500;

    public function index(Request $request): JsonResponse
    {
        $this->validateApiQuery($request, $this->phoneQueryRules());

        $fields = $this->requestedFields($request, self::LIST_FIELDS, self::FIELD_ALIASES, $this->allowedFields());
        $limit = $this->requestedLimit($request);

        $query = $this->buildListQuery($request);

        // Cursor (keyset) mode: stable, offset-free deep pagination. Chosen when
        // the client sends `cursor`, or opts in with `paginate=cursor`.
        if ($request->filled('cursor') || $request->query('paginate') === 'cursor') {
            return $this->cursorResponse($request, $query, $fields, $limit);
        }

        return $this->pageResponse($request, $query, $fields, $limit);
    }

    /**
     * Build the filtered (but unordered) published-products query shared by both
     * pagination modes.
     */
    private function buildListQuery(Request $request): Builder
    {
        return Product::query()
            ->where('status', 'published')
            ->when($request->filled('brand'), function (Builder $query) use ($request) {
                $this->applyBrandFilter($query, $request->query('brand'));
            })
            ->when($request->filled('ids'), function (Builder $query) use ($request) {
                $ids = collect($this->parseList($request->query('ids')))
                    ->map(fn (string $id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->take(self::MAX_LIST_ITEMS)
                    ->values()
                    ->all();

                if ($ids !== []) {
                    $query->whereIn('id', $ids);
                }
            })
            ->when($request->filled('name') || $request->filled('names'), function (Builder $query) use ($request) {
                $names = array_slice(array_values(array_unique(array_merge(
                    $this->parseList($request->query('name')),
                    $this->parseList($request->query('names')),
                ))), 0, self::MAX_LIST_ITEMS);

                if ($names !== []) {
                    $query->where(function (Builder $query) use ($names) {
                        foreach ($names as $name) {
                            $query->orWhere('name', $name);
                        }
                    });
                }
            })
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $query->search(mb_substr((string) $request->query('q'), 0, 191));
            });
    }

    /**
     * Legacy offset pagination. Kept for backwards compatibility; the page
     * number is hard-capped by the query validator to bound the OFFSET.
     */
    private function pageResponse(Request $request, Builder $query, array $fields, int $limit): JsonResponse
    {
        $page = $this->requestedPage($request);

        $total = $query->count();

        // Order and page in the database so a public request never hydrates the
        // entire catalog. sortPhoneList() then only refines ordering within the
        // already-bounded page (series/variant grouping of same-day releases).
        $this->applyListOrder($query);

        $phones = $this->sortPhoneList(
            $query->forPage($page, $limit)->get($this->selectColumns($fields))
        );

        return response()->json(
            $phones
                ->map(fn (Product $product) => $this->toItem($product, $fields))
                ->values()
        )->withHeaders([
            'X-Total-Count' => $total,
            'X-Per-Page' => $limit,
            'X-Current-Page' => $page,
            'X-Pagination-Mode' => 'page',
        ]);
    }

    /**
     * Keyset (cursor) pagination. Fetches one extra row to know whether a next
     * page exists, emits an opaque cursor for the last returned row, and never
     * uses OFFSET — so deep traversal stays O(limit) regardless of position.
     */
    private function cursorResponse(Request $request, Builder $query, array $fields, int $limit): JsonResponse
    {
        $total = (clone $query)->count();

        $cursor = null;
        if ($request->filled('cursor')) {
            $cursor = ListCursor::decode((string) $request->query('cursor'));

            if ($cursor === null) {
                abort(response()->json([
                    'message' => 'cursor 无效。',
                ], 422));
            }
        }

        $this->applyListOrder($query);
        $this->applyCursorConstraint($query, $cursor);

        // Fetch limit+1 to detect a following page without a second query.
        $rows = $query->take($limit + 1)->get($this->selectColumns(array_merge($fields, ['id'])));

        $hasMore = $rows->count() > $limit;
        $pageRows = $rows->take($limit)->values();

        $last = $pageRows->last();
        $nextCursor = ($hasMore && $last instanceof Product)
            ? ListCursor::encode($this->cursorKeyFor($last))
            : null;

        // In-page fine ordering only; never reorders across the page boundary,
        // so the emitted cursor still matches the DB keyset order.
        $phones = $this->sortPhoneList($pageRows);

        return response()->json([
            'data' => $phones
                ->map(fn (Product $product) => $this->toItem($product, $fields))
                ->values(),
            'meta' => [
                'nextCursor' => $nextCursor,
                'hasMore' => $hasMore,
                'perPage' => $limit,
                'total' => $total,
            ],
        ])->withHeaders([
            'X-Total-Count' => $total,
            'X-Per-Page' => $limit,
            'X-Pagination-Mode' => 'cursor',
        ]);
    }

    /**
     * The keyset tuple for a row, matching applyListOrder()'s ordering:
     * (undated flag, release_date, name, id).
     *
     * @return array{f: int, rd: int, n: string, id: int}
     */
    private function cursorKeyFor(Product $product): array
    {
        $rd = (int) ($product->release_date ?? 0);

        return [
            'f' => $rd > 0 ? 0 : 1,
            'rd' => $rd,
            'n' => (string) $product->name,
            'id' => (int) $product->id,
        ];
    }

    /**
     * Add the "row strictly after the cursor" constraint, expanded from the
     * composite ordering (undated flag ASC, release_date DESC, name ASC,
     * id ASC) into a lexicographic comparison.
     *
     * @param  array{f: int, rd: int, n: string, id: int}|null  $cursor
     */
    private function applyCursorConstraint(Builder $query, ?array $cursor): void
    {
        if ($cursor === null) {
            return;
        }

        // COALESCE folds NULL release dates to 0 so comparisons never trip
        // over SQL NULL semantics; the flag expression mirrors applyListOrder().
        $flagExpr = '(CASE WHEN release_date IS NULL OR release_date = 0 THEN 1 ELSE 0 END)';
        $rdExpr = 'COALESCE(release_date, 0)';

        $query->where(function (Builder $q) use ($cursor, $flagExpr, $rdExpr) {
            // f ASC
            $q->whereRaw("{$flagExpr} > ?", [$cursor['f']])
                ->orWhere(function (Builder $q) use ($cursor, $flagExpr, $rdExpr) {
                    // same f, release_date DESC
                    $q->whereRaw("{$flagExpr} = ?", [$cursor['f']])
                        ->where(function (Builder $q) use ($cursor, $rdExpr) {
                            $q->whereRaw("{$rdExpr} < ?", [$cursor['rd']])
                                ->orWhere(function (Builder $q) use ($cursor, $rdExpr) {
                                    // same release_date, name ASC
                                    $q->whereRaw("{$rdExpr} = ?", [$cursor['rd']])
                                        ->where(function (Builder $q) use ($cursor) {
                                            $q->where('name', '>', $cursor['n'])
                                                ->orWhere(function (Builder $q) use ($cursor) {
                                                    // same name, id ASC
                                                    $q->where('name', '=', $cursor['n'])
                                                        ->where('id', '>', $cursor['id']);
                                                });
                                        });
                                });
                        });
                });
        });
    }

    public function search(Request $request): JsonResponse
    {
        $this->validateApiQuery($request, array_merge($this->phoneQueryRules(), [
            'q' => ['required', 'string', 'max:191'],
        ]));

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
        $this->validateApiQuery($request, array_merge($this->phoneQueryRules(), [
            'slug' => ['required', 'string', 'max:191'],
        ]));

        $slug = Product::normalizeSlug($request->query('slug'));

        $product = Product::query()
            ->where('status', 'published')
            ->where('slug_key', $slug)
            ->when($request->filled('brand'), function (Builder $query) use ($request) {
                $this->applyBrandFilter($query, $request->query('brand'));
            })
            ->orderBy('id')
            ->first();

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
            'imgurl' => $product->safe_image_url,
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
        $value = data_get($product->specs, $key, '');

        if ($key === 'official') {
            return SafeUrl::sanitize((string) $value) ?? '';
        }

        return $value;
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

    /**
     * Primary ordering pushed to the database: dated releases first (newest
     * first), then undated rows by name, with `id` as the final tie-breaker so
     * paging is deterministic. sortPhoneList() layers the fine-grained
     * series/variant ordering on top, within each same-day group of the page.
     */
    private function applyListOrder(Builder $query): void
    {
        $query
            ->orderByRaw('CASE WHEN release_date IS NULL OR release_date = 0 THEN 1 ELSE 0 END')
            ->orderByDesc('release_date')
            ->orderBy('name')
            ->orderBy('id');
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

    private function requestedLimit(Request $request): int
    {
        if (! $request->filled('limit')) {
            return self::DEFAULT_LIMIT;
        }

        $limit = (int) $request->query('limit');

        if ($limit < 1) {
            abort(response()->json([
                'message' => 'limit 至少为 1。',
            ], 422));
        }

        return min($limit, self::MAX_LIMIT);
    }

    private function requestedPage(Request $request): int
    {
        return max((int) $request->query('page', 1), 1);
    }
}
