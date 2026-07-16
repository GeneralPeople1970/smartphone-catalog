<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL FULLTEXT index (ngram parser) on products.search_text, powering the
 * optional `fulltext` search driver (config catalog.search.driver).
 *
 * MySQL-only: the index is created only on a MySQL connection. On SQLite (tests
 * / local) this migration is a no-op and search stays on the LIKE driver, which
 * is the safe cross-database fallback — so `migrate` succeeds on every platform.
 *
 * ngram (token size 2, MySQL default) makes FULLTEXT work for unspaced Chinese
 * text; verified on MySQL 5.7.26 that `MATCH ... AGAINST('"骁龙"' IN BOOLEAN
 * MODE)` returns the same rows as the previous `LIKE '%骁龙%'`.
 *
 * After deploying with CATALOG_SEARCH_DRIVER=fulltext, rebuild is automatic on
 * insert/update; to force a full rebuild run `OPTIMIZE TABLE products;`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Guard against re-runs / partial state.
        if ($this->fulltextIndexExists()) {
            return;
        }

        DB::statement('ALTER TABLE products ADD FULLTEXT INDEX products_search_text_fulltext (search_text) WITH PARSER ngram');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if ($this->fulltextIndexExists()) {
            Schema::table('products', function ($table) {
                $table->dropIndex('products_search_text_fulltext');
            });
        }
    }

    private function fulltextIndexExists(): bool
    {
        $rows = DB::select(
            "SHOW INDEX FROM products WHERE Key_name = 'products_search_text_fulltext'"
        );

        return $rows !== [];
    }
};
