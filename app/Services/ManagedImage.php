<?php

namespace App\Services;

use App\Models\HomepageSlide;
use App\Models\Product;
use App\Support\ImageUrl;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ManagedImage
{
    public function deleteUnreferenced(?string $reference): void
    {
        $path = ImageUrl::managedPath($reference);
        if ($path === null) {
            return;
        }
        foreach ([[HomepageSlide::class, 'image_path'], [Product::class, 'image_url']] as [$model, $column]) {
            foreach ($model::query()->whereNotNull($column)->cursor() as $record) {
                if (ImageUrl::managedPath($record->$column) === $path) {
                    return;
                }
            }
        }
        Storage::disk('public')->delete(Str::after($path, '/storage/'));
    }
}
