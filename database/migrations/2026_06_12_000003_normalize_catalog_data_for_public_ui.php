<?php

use App\Models\Product;
use App\Support\PhoneCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $this->normalizeProducts();
        $this->normalizeHomepageSlides();
        $this->normalizeFeaturedPhones();
    }

    public function down(): void
    {
        // This migration intentionally normalizes production data in place.
    }

    private function normalizeProducts(): void
    {
        Product::query()
            ->orderBy('id')
            ->chunkById(200, function ($products) {
                foreach ($products as $product) {
                    $brand = PhoneCatalog::canonicalBrandName($product->brand, $product->source_file);
                    $imageUrl = $this->nullableText($product->image_url);
                    $price = $this->normalizePrice($product->price);
                    $battery = $this->normalizeBattery($product->battery_capacity)
                        ?? $this->normalizeBattery(data_get($product->specs, 'battery'));
                    $specs = is_array($product->specs) ? $product->specs : [];

                    if ($product->brand && $product->brand !== $brand) {
                        $specs['source_company'] ??= $product->brand;
                    }

                    $specCompany = trim((string) data_get($specs, 'company', ''));

                    if ($specCompany !== '' && $specCompany !== $brand) {
                        $specs['source_company'] ??= $specCompany;
                    }

                    $product->forceFill([
                        'brand' => $brand,
                        'name' => $this->cleanText($product->name),
                        'slug' => $this->productSlug($product->id, $brand, $product->name),
                        'image_url' => $imageUrl,
                        'price' => $price,
                        'soc_name' => $this->nullableText($product->soc_name),
                        'battery_capacity' => $battery,
                        'specs' => Product::syncSpecsWithFields($specs, [
                            'id' => $product->id,
                            'brand' => $brand,
                            'name' => $product->name,
                            'image_url' => $imageUrl,
                            'price' => $price,
                            'soc_name' => $product->soc_name,
                            'battery_capacity' => $battery,
                        ]),
                    ])->save();
                }
            });
    }

    private function normalizeHomepageSlides(): void
    {
        if (! Schema::hasTable('homepage_slides')) {
            return;
        }

        $slides = DB::table('homepage_slides')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($slides as $index => $slide) {
            $title = $this->cleanText($slide->title);
            $imagePath = $this->normalizeSlideImagePath($slide->image_path);

            DB::table('homepage_slides')
                ->where('id', $slide->id)
                ->update([
                    'title' => $title === '' || preg_match('/^\d+$/', $title)
                        ? '首页焦点 '.($index + 1)
                        : $title,
                    'image_path' => $imagePath,
                    'updated_at' => now(),
                ]);
        }
    }

    private function normalizeFeaturedPhones(): void
    {
        if (! Schema::hasTable('homepage_featured_phones')) {
            return;
        }

        $featuredPhones = DB::table('homepage_featured_phones')
            ->join('products', 'products.id', '=', 'homepage_featured_phones.product_id')
            ->select([
                'homepage_featured_phones.id',
                'homepage_featured_phones.title',
                'homepage_featured_phones.description',
                'products.brand',
                'products.name',
                'products.soc_name',
                'products.battery_capacity',
                'products.specs',
            ])
            ->orderBy('homepage_featured_phones.sort_order')
            ->orderBy('homepage_featured_phones.id')
            ->get();

        foreach ($featuredPhones as $item) {
            $specs = json_decode((string) $item->specs, true) ?: [];
            $feature = $this->cleanText(data_get($specs, 'feature'));
            $description = $this->cleanText($item->description)
                ?: $feature
                ?: $this->featuredDescription($item);

            DB::table('homepage_featured_phones')
                ->where('id', $item->id)
                ->update([
                    'title' => $this->cleanText($item->title) ?: $item->name,
                    'description' => $description,
                    'updated_at' => now(),
                ]);
        }
    }

    private function normalizeSlideImagePath(?string $imagePath): ?string
    {
        $imagePath = $this->nullableText($imagePath);

        if (! $imagePath || ! Str::startsWith($imagePath, '/dist/img/homepage/')) {
            return $imagePath;
        }

        $filename = basename($imagePath);
        $sourcePath = public_path(ltrim($imagePath, '/'));

        if (! File::exists($sourcePath)) {
            return $imagePath;
        }

        $target = 'homepage/'.$filename;

        if (Storage::disk('public')->exists($target) && Storage::disk('public')->get($target) !== File::get($sourcePath)) {
            $target = 'homepage/'.pathinfo($filename, PATHINFO_FILENAME).'-'.Str::random(6).'.'.pathinfo($filename, PATHINFO_EXTENSION);
        }

        Storage::disk('public')->put($target, File::get($sourcePath));

        return '/storage/'.$target;
    }

    private function productSlug(int $id, string $brand, string $name): string
    {
        $brandCode = strtolower(PhoneCatalog::codeForBrand($brand));
        $nameSlug = Str::slug($name);
        $slug = trim($brandCode.'-'.$id.($nameSlug ? '-'.$nameSlug : ''), '-');

        return Str::limit($slug !== '' ? $slug : 'phone-'.$id, 191, '');
    }

    private function featuredDescription(object $item): string
    {
        $parts = array_filter([
            $item->brand,
            $item->soc_name,
            $item->battery_capacity ? $item->battery_capacity.' mAh' : null,
        ]);

        return implode(' · ', $parts);
    }

    private function normalizePrice(mixed $value): ?string
    {
        $price = $this->cleanText($value);

        return $price === '' || in_array($price, ['0', '0.0', '0.00', '暂无', '暂无价格', '暂无报价', '待定'], true) ? null : $price;
    }

    private function normalizeBattery(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $battery = (int) $value;

            return $battery > 0 ? $battery : null;
        }

        preg_match('/(\d{3,5})\s*mAh/i', (string) $value, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = $this->cleanText($value);

        return $text === '' ? null : $text;
    }

    private function cleanText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
};
