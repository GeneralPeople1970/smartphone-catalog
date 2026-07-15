<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist a normalized slug lookup key so phone detail resolves a single
     * row with an indexed WHERE instead of loading a brand's products and
     * scanning them in PHP.
     *
     * Non-unique on purpose: two names can normalize to the same key, and the
     * lookup takes the lowest id, preserving the previous "first match wins"
     * behavior. The canonical, unique `slug` column is unchanged.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug_key')->nullable()->after('slug')->index();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['slug_key']);
            $table->dropColumn('slug_key');
        });
    }

    /**
     * Populate the new column from existing rows. Saving each model runs the
     * Product `saving` hook, which derives slug_key from slug/name.
     */
    private function backfill(): void
    {
        Product::query()
            ->orderBy('id')
            ->chunkById(200, function ($products): void {
                foreach ($products as $product) {
                    $product->timestamps = false;
                    $product->save();
                }
            });
    }
};
