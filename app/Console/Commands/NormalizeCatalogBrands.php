<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\PhoneCatalog;
use Illuminate\Console\Command;

class NormalizeCatalogBrands extends Command
{
    protected $signature = 'catalog:normalize-brands {--apply : Apply unambiguous aliases after reporting}';

    protected $description = 'Report brand aliases and provenance conflicts; optionally normalize unambiguous aliases';

    public function handle(): int
    {
        $updated = 0;
        foreach (Product::query()->orderBy('id')->lazyById(200) as $product) {
            $brand = PhoneCatalog::entryForInput($product->brand);
            $source = PhoneCatalog::entryForSourceFile($product->source_file);
            if ($brand === null || ($source !== null && $source['code'] !== $brand['code'])) {
                $this->warn("Review #{$product->id}: {$product->brand} / {$product->source_file}");

                continue;
            }
            if ($product->brand === $brand['name']) {
                continue;
            }
            $this->line("#{$product->id}: {$product->brand} -> {$brand['name']}");
            if ($this->option('apply')) {
                $product->getConnection()->transaction(function () use ($product, $brand, &$updated) {
                    $fresh = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                    if ($fresh->brand !== $product->brand || $fresh->source_file !== $product->source_file) {
                        return;
                    }
                    $specs = $fresh->specsForEditing();
                    $specs['source_company'] ??= $fresh->brand;
                    $fresh->brand = $brand['name'];
                    $fresh->specs = $specs;
                    $fresh->specs = $fresh->specsForEditing();
                    $fresh->save();
                    $updated++;
                });
            }
        }
        $this->info($this->option('apply') ? "Updated {$updated} aliases." : 'Report only. No data changed.');

        return self::SUCCESS;
    }
}
