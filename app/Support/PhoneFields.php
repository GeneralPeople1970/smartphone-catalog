<?php

namespace App\Support;

use App\Models\Product;

class PhoneFields
{
    public const LIST = ['id', 'phonename', 'company', 'companyCode', 'socname', 'price', 'battery', 'imgurl'];

    public const SPECS = [
        'screenm', 'charge', 'storeage', 'weight', 'feature', 'saledate', 'official',
        'cpu', 'gpu', 'ramfadsf', 'romagbcz', 'wifi', 'bluetooth', 'screencolor',
        'location', 'osui', 'material', 'sensor',
    ];

    public const EXTRA = ['slug', 'brandLogo', 'displayPrice'];

    public const ALIASES = [
        'name' => 'phonename', 'model' => 'phonename', 'phoneName' => 'phonename',
        'brand' => 'company', 'brandCode' => 'companyCode', 'processor' => 'socname',
        'soc' => 'socname', 'image' => 'imgurl', 'imageUrl' => 'imgurl',
        'storage' => 'storeage', 'releaseDate' => 'saledate',
    ];

    public const EDIT_MAP = [
        'brand' => 'company', 'name' => 'phonename', 'image_url' => 'imgurl',
        'price' => 'price', 'soc_name' => 'socname', 'battery_capacity' => 'battery',
    ];

    public static function columns(array $fields): array
    {
        $columns = ['id', 'name', 'brand', 'soc_name', 'price', 'battery_capacity', 'image_url', 'slug', 'release_date'];
        if (array_intersect($fields, self::SPECS) !== []) {
            $columns[] = 'specs';
        }

        return $columns;
    }

    public static function values(Product $product): array
    {
        $brand = PhoneCatalog::entryForInput($product->brand);
        $price = trim((string) $product->price);
        $values = [
            'id' => $product->id, 'phonename' => $product->name,
            'company' => $brand['displayName'] ?? $product->brand,
            'companyCode' => $brand['code'] ?? PhoneCatalog::codeForBrand($product->brand),
            'socname' => $product->soc_name,
            'price' => $price === '' ? null : (ctype_digit($price) ? self::numberOrText($price) : $price),
            'displayPrice' => $product->display_price, 'battery' => $product->battery_capacity,
            'imgurl' => $product->safe_image_url, 'slug' => $product->slug,
            'brandLogo' => $brand['logo'] ?? null,
        ];
        foreach (self::SPECS as $field) {
            $value = data_get($product->specs, $field, '');
            $value = is_scalar($value) ? $value : '';
            $values[$field] = $field === 'official' ? (SafeUrl::sanitize((string) $value) ?? '') : $value;
        }

        return $values;
    }

    /** Keep text when a numeric conversion would overflow or change its digits. */
    public static function numberOrText(string $value): int|float|string
    {
        $integerText = ctype_digit($value) ? (ltrim($value, '0') ?: '0') : $value;
        $integer = filter_var($integerText, FILTER_VALIDATE_INT);
        if ($integer !== false) {
            return $integer;
        }

        if (preg_match('/^-?\d+\.\d+$/D', $value)) {
            $number = (float) $value;
            if (is_finite($number) && (string) $number === $value) {
                return $number;
            }
        }

        return $value;
    }
}
