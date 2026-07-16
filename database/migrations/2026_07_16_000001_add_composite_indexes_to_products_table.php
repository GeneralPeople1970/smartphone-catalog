<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite index for the brand list query, verified with EXPLAIN on the
 * production-shaped MySQL 5.7 dataset (1.5k rows) before adoption:
 *
 * - (status, brand, release_date): brand pages filter `status='published'
 *   AND brand IN (...)` and order by release_date. EXPLAIN switches from the
 *   single-column brand index to this composite ("Using index", 86 rows
 *   examined for an 86-row brand) — a clear win that scales with catalog size.
 *
 * - (status, release_date) was evaluated and REJECTED: the default list's
 *   ORDER BY starts with a CASE expression (undated-rows-last flag), which no
 *   MySQL 5.7 B-tree can serve, so EXPLAIN kept "Using filesort" and the
 *   optimizer still chose the existing single-column status index. Adding it
 *   would duplicate that index's prefix without changing any plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['status', 'brand', 'release_date'], 'products_status_brand_release_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_status_brand_release_date_index');
        });
    }
};
