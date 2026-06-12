<?php

use App\Support\PhoneCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        DB::table('products')
            ->select(['id', 'brand', 'source_file', 'specs'])
            ->orderBy('id')
            ->chunkById(200, function ($products): void {
                foreach ($products as $product) {
                    $entry = PhoneCatalog::entryForProduct((string) $product->brand, $product->source_file);
                    $brand = $entry['name'] ?? PhoneCatalog::canonicalBrandName($product->brand, $product->source_file);
                    $sourceFile = $entry['sourceFile'] ?? PhoneCatalog::canonicalSourceFile($brand, $product->source_file);
                    $specs = $this->decodeSpecs($product->specs);

                    if ($product->brand && $product->brand !== $brand) {
                        $specs['source_company'] ??= $product->brand;
                    }

                    $specCompany = trim((string) ($specs['company'] ?? ''));

                    if ($specCompany !== '' && $specCompany !== $brand) {
                        $specs['source_company'] ??= $specCompany;
                    }

                    if ($product->source_file && $sourceFile && $product->source_file !== $sourceFile) {
                        $specs['source_file_original'] ??= $product->source_file;
                    }

                    $specs['company'] = $brand;

                    DB::table('products')
                        ->where('id', $product->id)
                        ->update([
                            'brand' => $brand,
                            'source_file' => $sourceFile,
                            'specs' => json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // One-way data normalization. Original values are preserved in specs.source_company
        // and specs.source_file_original for traceability.
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSpecs(mixed $specs): array
    {
        if (is_array($specs)) {
            return $specs;
        }

        $decoded = json_decode((string) $specs, true);

        return is_array($decoded) ? $decoded : [];
    }
};
