<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomepageFeaturedPhone;
use App\Models\Product;
use App\Support\PhoneCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomepageFeaturedPhoneController extends Controller
{
    private const DEFAULT_FIELDS = [
        'id',
        'phonename',
        'company',
        'companyCode',
        'socname',
        'price',
        'displayPrice',
        'battery',
        'imgurl',
        'feature',
        'slug',
        'recommendTitle',
        'recommendDescription',
        'sortOrder',
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
        'title' => 'recommendTitle',
        'description' => 'recommendDescription',
        'sort' => 'sortOrder',
        'sort_order' => 'sortOrder',
    ];

    private const PHONE_FIELDS = [
        'id',
        'phonename',
        'company',
        'companyCode',
        'socname',
        'price',
        'displayPrice',
        'battery',
        'imgurl',
        'feature',
        'slug',
        'saledate',
        'brandLogo',
    ];

    private const RECOMMEND_FIELDS = [
        'recommendTitle',
        'recommendDescription',
        'sortOrder',
    ];

    public function index(Request $request): JsonResponse
    {
        $fields = $this->requestedFields($request);

        $phones = HomepageFeaturedPhone::query()
            ->with('product')
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('status', 'published'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HomepageFeaturedPhone $featuredPhone) => $this->toItem($featuredPhone, $fields))
            ->values();

        return response()->json($phones);
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function toItem(HomepageFeaturedPhone $featuredPhone, array $fields): array
    {
        $values = $this->fieldValues($featuredPhone);

        return collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $values[$field] ?? ''])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldValues(HomepageFeaturedPhone $featuredPhone): array
    {
        /** @var Product $product */
        $product = $featuredPhone->product;
        $brand = PhoneCatalog::entryForProduct($product->brand, $product->source_file);

        return [
            'id' => $product->id,
            'phonename' => $product->name,
            'company' => $brand['displayName'] ?? $product->brand,
            'companyCode' => $brand['code'] ?? PhoneCatalog::codeForBrand($product->brand),
            'socname' => $product->soc_name,
            'price' => $this->price($product),
            'displayPrice' => $product->display_price,
            'battery' => $product->battery_capacity,
            'imgurl' => $product->image_url,
            'feature' => data_get($product->specs, 'feature', ''),
            'slug' => $product->slug,
            'saledate' => data_get($product->specs, 'saledate', ''),
            'brandLogo' => $brand['logo'] ?? null,
            'recommendTitle' => $featuredPhone->title ?: $product->name,
            'recommendDescription' => $featuredPhone->description ?: data_get($product->specs, 'feature', ''),
            'sortOrder' => $featuredPhone->sort_order,
        ];
    }

    private function price(Product $product): int|string|null
    {
        $price = trim((string) $product->price);

        if ($price === '') {
            return null;
        }

        return ctype_digit($price) ? (int) $price : $price;
    }

    /**
     * @return array<int, string>
     */
    private function requestedFields(Request $request): array
    {
        $fields = $this->parseList($request->query('fields'));

        if ($fields === []) {
            return self::DEFAULT_FIELDS;
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
        return array_values(array_unique(array_merge(self::PHONE_FIELDS, self::RECOMMEND_FIELDS)));
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
