<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiFields;
use App\Http\Controllers\Controller;
use App\Models\HomepageFeaturedPhone;
use App\Models\Product;
use App\Support\PhoneCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomepageFeaturedPhoneController extends Controller
{
    use ResolvesApiFields;

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
        $fields = $this->requestedFields($request, self::DEFAULT_FIELDS, self::FIELD_ALIASES, $this->allowedFields());

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
        return $this->onlyFields($this->fieldValues($featuredPhone), $fields);
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
            'price' => $this->price($product->price),
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

    /**
     * @return array<int, string>
     */
    private function allowedFields(): array
    {
        return array_values(array_unique(array_merge(self::PHONE_FIELDS, self::RECOMMEND_FIELDS)));
    }
}
