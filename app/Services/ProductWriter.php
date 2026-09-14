<?php

namespace App\Services;

use App\Models\Product;
use App\Support\PhoneCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductWriter
{
    public function save(array $data, ?Product $product = null): Product
    {
        try {
            return DB::transaction(function () use ($data, $product) {
                $product ??= new Product;
                if (isset($data['id']) && ! $product->exists) {
                    $product->id = $data['id'];
                }
                $product->fill(Arr::except($data, ['id']))->save();
                $base = trim((string) $product->slug);
                if ($base === '') {
                    $base = strtolower(PhoneCatalog::codeForBrand($product->brand)).'-'.$product->id.'-'.Str::slug($product->name);
                }
                $base = self::normalizeSlug($base) ?: 'phone-'.$product->id;
                $slug = $base;
                for ($index = 2; Product::where('slug', $slug)->whereKeyNot($product->id)->exists(); $index++) {
                    $suffix = '-'.$index;
                    $slug = Str::limit($base, 191 - strlen($suffix), '').$suffix;
                }
                $product->slug = $slug;
                $product->specs = $product->specsForEditing();
                if ($product->isDirty()) {
                    $product->save();
                }

                return $product;
            });
        } catch (UniqueConstraintViolationException $e) {
            $constraint = strtolower((string) ($e->errorInfo[2] ?? ''));
            $field = match (true) {
                str_contains($constraint, 'products.id'), str_contains($constraint, 'primary') => 'id',
                str_contains($constraint, 'source_key') => 'source_key',
                default => 'slug',
            };
            throw ValidationException::withMessages([$field => 'ID、来源或 URL 标识已存在，请刷新后重试。']);
        }
    }

    public static function normalizeSlug(string $value): string
    {
        $slug = Str::slug($value);
        if ($slug !== '') {
            return Str::limit($slug, 180, '');
        }
        $fallback = strtolower(PhoneCatalog::compactKeyword($value));

        return trim(Str::limit(preg_replace('/[^\p{Han}a-z0-9]+/iu', '-', $fallback) ?? '', 180, ''), '-');
    }
}
