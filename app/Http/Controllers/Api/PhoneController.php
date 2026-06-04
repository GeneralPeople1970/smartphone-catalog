<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\PhoneCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PhoneController extends Controller
{
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
        'remark',
        'updateTime',
        'saletime',
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

    private const SEARCH_ALIASES = [
        'xiaomi' => ['小米'],
        'mi' => ['小米'],
        'redmi' => ['红米'],
        'apple' => ['苹果', 'iPhone', 'iPad'],
        'iphone' => ['iPhone', '苹果'],
        'ipad' => ['iPad', '苹果'],
        'huawei' => ['华为'],
        'honor' => ['荣耀'],
        'samsung' => ['三星'],
        'meizu' => ['魅族'],
        'realme' => ['realme', '真我'],
        'oneplus' => ['一加'],
        'nubia' => ['努比亚'],
        'sony' => ['索尼'],
        'zte' => ['中兴'],
        'asus' => ['华硕'],
        'google' => ['谷歌'],
        'nokia' => ['诺基亚'],
        'motorola' => ['摩托罗拉'],
        'moto' => ['摩托罗拉'],
        'lenovo' => ['联想', '联想小新'],
        'qualcomm snapdragon' => ['骁龙', '高通骁龙'],
        'snapdragon' => ['骁龙'],
        'qualcomm' => ['高通', '骁龙'],
        '高通骁龙' => ['骁龙', 'Qualcomm Snapdragon'],
        '高通' => ['骁龙', 'Qualcomm'],
        '骁龙' => ['Snapdragon', 'Qualcomm Snapdragon'],
        'dimensity' => ['天玑'],
        'mediatek' => ['联发科', '天玑'],
        '联发科' => ['天玑', 'MediaTek'],
        '天玑' => ['Dimensity', '联发科'],
        'kirin' => ['麒麟'],
        '麒麟' => ['Kirin'],
        'exynos' => ['猎户座'],
        'bionic' => ['仿生', '苹果 A'],
    ];

    public function index(Request $request): JsonResponse
    {
        $fields = $this->requestedFields($request, self::LIST_FIELDS);
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
                $this->applyKeywordFilter($query, $request->query('q'));
            })
            ->orderBy('id');

        $phones = $this->sortPhoneList($query->get());

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
            $request->query->set('fields', 'id,phonename,company,companyCode,socname,price,displayPrice,battery,imgurl,slug,saledate');
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
            $this->requestedFields($request, array_merge(self::LIST_FIELDS, self::SPEC_FIELDS))
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

        $product = $products->first(fn (Product $product) => $this->normalizeSlug($product->name) === $slug);

        abort_if(! $product, 404);

        return response()->json($this->toItem(
            $product,
            $this->requestedFields($request, array_merge(self::LIST_FIELDS, self::SPEC_FIELDS))
        ));
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function toItem(Product $product, array $fields): array
    {
        $values = $this->fieldValues($product);

        return collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $values[$field] ?? ''])
            ->all();
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
            'price' => $this->price($product),
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
        return $products->sort(function (Product $left, Product $right) {
            $leftDate = $this->releaseDate($left);
            $rightDate = $this->releaseDate($right);

            if (($leftDate > 0) !== ($rightDate > 0)) {
                return $leftDate > 0 ? -1 : 1;
            }

            if ($leftDate === 0 && $rightDate === 0) {
                return strnatcasecmp($left->name, $right->name);
            }

            if ($leftDate !== $rightDate) {
                return $rightDate <=> $leftDate;
            }

            $leftSeries = $this->seriesKey($left);
            $rightSeries = $this->seriesKey($right);

            if ($leftSeries !== $rightSeries) {
                return strnatcasecmp($leftSeries, $rightSeries);
            }

            $variantComparison = $this->variantRank($left->name) <=> $this->variantRank($right->name);

            if ($variantComparison !== 0) {
                return $variantComparison;
            }

            return strnatcasecmp($left->name, $right->name);
        });
    }

    private function releaseDate(Product $product): int
    {
        $date = (int) data_get($product->specs, 'saledate', 0);

        return $date > 0 ? $date : 0;
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

    private function price(Product $product): int|string|null
    {
        $price = trim((string) $product->price);

        if ($price === '') {
            return null;
        }

        return ctype_digit($price) ? (int) $price : $price;
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
            $query->whereIn('source_file', $entry['sourceFiles']);

            return;
        }

        $query->whereIn('brand', PhoneCatalog::resolveBrandNames($brand));
    }

    private function applyKeywordFilter(Builder $query, mixed $keyword): void
    {
        $keywords = $this->expandSearchKeywords((string) $keyword);

        $query->where(function (Builder $query) use ($keywords) {
            foreach ($keywords as $keyword) {
                $like = '%'.$keyword.'%';
                $compact = $this->compactKeyword($keyword);

                $query->orWhere('name', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('soc_name', 'like', $like)
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(specs, '$.cpu')) LIKE ?", [$like])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(specs, '$.gpu')) LIKE ?", [$like])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(specs, '$.feature')) LIKE ?", [$like]);

                if ($compact !== $keyword) {
                    $compactLike = '%'.$compact.'%';

                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(name, ' ', ''), '-', ''), '_', '') LIKE ?", [$compactLike])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(brand, ' ', ''), '-', ''), '_', '') LIKE ?", [$compactLike])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(soc_name, ' ', ''), '-', ''), '_', '') LIKE ?", [$compactLike]);
                }
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function expandSearchKeywords(string $keyword): array
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return [];
        }

        $keywords = [$keyword];
        $lowerKeyword = mb_strtolower($keyword);

        foreach (self::SEARCH_ALIASES as $alias => $replacements) {
            $lowerAlias = mb_strtolower($alias);

            if (str_contains($lowerKeyword, $lowerAlias)) {
                foreach ($replacements as $replacement) {
                    $keywords[] = str_ireplace($alias, $replacement, $keyword);

                    if ($lowerKeyword === $lowerAlias) {
                        $keywords[] = $replacement;
                    }
                }
            }
        }

        return collect($keywords)
            ->flatMap(fn (string $keyword) => [$keyword, $this->compactKeyword($keyword)])
            ->map(fn (string $keyword) => trim($keyword))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function compactKeyword(string $keyword): string
    {
        return preg_replace('/[\s\-_]+/u', '', $keyword) ?? $keyword;
    }

    /**
     * @param  array<int, string>  $defaultFields
     * @return array<int, string>
     */
    private function requestedFields(Request $request, array $defaultFields): array
    {
        $fields = $this->parseList($request->query('fields'));

        if ($fields === []) {
            return $defaultFields;
        }

        $fields = collect($fields)
            ->map(fn (string $field) => self::FIELD_ALIASES[$field] ?? $field)
            ->unique()
            ->values()
            ->all();
        $allowed = $this->allowedFields();
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
}
