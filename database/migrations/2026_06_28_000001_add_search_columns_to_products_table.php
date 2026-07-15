<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promote the hot `specs` fields to dedicated columns.
     *
     * `release_date` is indexed and drives list ordering / homepage sorting.
     * `search_text` is a denormalized TEXT column that replaces the old
     * multi-field JSON_EXTRACT search with a single `LIKE %term%` scan — it is
     * deliberately NOT indexed, because a B-tree cannot serve a leading-wildcard
     * LIKE. See docs/DEVELOPMENT.md ("搜索与性能") for the scaling path.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('release_date')->nullable()->after('battery_capacity')->index();
            $table->text('search_text')->nullable()->after('specs');
            $table->index('source_file');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['release_date']);
            $table->dropIndex(['source_file']);
            $table->dropColumn(['release_date', 'search_text']);
        });
    }

    /**
     * Populate the new columns from existing rows. Saving each model runs the
     * Product `saving` hook, which derives release_date/search_text from specs.
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
