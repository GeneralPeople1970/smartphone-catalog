<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'source_key',
        'source_file',
        'source_id',
        'brand',
        'name',
        'slug',
        'image_url',
        'price',
        'soc_name',
        'battery_capacity',
        'status',
        'specs',
    ];

    protected function casts(): array
    {
        return [
            'battery_capacity' => 'integer',
            'specs' => 'array',
        ];
    }

    public function getDisplayPriceAttribute(): string
    {
        $price = trim((string) $this->price);

        if ($price === '' || in_array($price, ['0', '0.0', '0.00'], true)) {
            return '暂无价格';
        }

        return $price;
    }

    /**
     * @return array<string, mixed>
     */
    public function specsForEditing(): array
    {
        if (! $this->exists && empty($this->specs)) {
            return [];
        }

        return self::syncSpecsWithFields($this->specs ?? [], [
            'id' => $this->exists ? $this->getKey() : null,
            'brand' => $this->brand,
            'name' => $this->name,
            'image_url' => $this->image_url,
            'price' => $this->price,
            'soc_name' => $this->soc_name,
            'battery_capacity' => $this->battery_capacity,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $specs
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function syncSpecsWithFields(?array $specs, array $fields): array
    {
        $specs ??= [];

        if (($fields['id'] ?? null) !== null) {
            $specs['id'] = (int) $fields['id'];
        }

        $specs['company'] = trim((string) ($fields['brand'] ?? ''));
        $specs['phonename'] = trim((string) ($fields['name'] ?? ''));
        $specs['imgurl'] = trim((string) ($fields['image_url'] ?? ''));
        $specs['price'] = self::numericSpecValue($fields['price'] ?? 0);
        $specs['socname'] = trim((string) ($fields['soc_name'] ?? ''));
        $specs['battery'] = self::numericSpecValue($fields['battery_capacity'] ?? 0);

        return $specs;
    }

    private static function numericSpecValue(mixed $value): int|float|string
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $value = trim((string) $value);

        if (! is_numeric($value)) {
            return $value;
        }

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }
}
