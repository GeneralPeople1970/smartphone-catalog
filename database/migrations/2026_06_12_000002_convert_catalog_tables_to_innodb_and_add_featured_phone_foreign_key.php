<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FOREIGN_KEY = 'homepage_featured_phones_product_id_foreign';

    /**
     * @var array<int, string>
     */
    private array $catalogTables = [
        'products',
        'homepage_featured_phones',
        'homepage_slides',
        'site_settings',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->assertNoOrphanedFeaturedPhones();
        $this->convertTablesToEngine('InnoDB');
        $this->addFeaturedPhoneForeignKey();
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropFeaturedPhoneForeignKey();
        $this->convertTablesToEngine('MyISAM');
    }

    private function assertNoOrphanedFeaturedPhones(): void
    {
        if (! Schema::hasTable('homepage_featured_phones') || ! Schema::hasTable('products')) {
            return;
        }

        $orphanCount = DB::table('homepage_featured_phones')
            ->leftJoin('products', 'homepage_featured_phones.product_id', '=', 'products.id')
            ->whereNull('products.id')
            ->count();

        if ($orphanCount > 0) {
            throw new RuntimeException("Cannot add featured phone foreign key: {$orphanCount} orphaned product reference(s) exist.");
        }
    }

    private function convertTablesToEngine(string $engine): void
    {
        foreach ($this->catalogTables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` ENGINE={$engine}");
            }
        }
    }

    private function addFeaturedPhoneForeignKey(): void
    {
        if (
            ! Schema::hasTable('homepage_featured_phones') ||
            ! Schema::hasTable('products') ||
            $this->foreignKeyExists(self::FOREIGN_KEY)
        ) {
            return;
        }

        DB::statement(
            'ALTER TABLE `homepage_featured_phones` '.
            'ADD CONSTRAINT `'.self::FOREIGN_KEY.'` '.
            'FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE'
        );
    }

    private function dropFeaturedPhoneForeignKey(): void
    {
        if (! Schema::hasTable('homepage_featured_phones') || ! $this->foreignKeyExists(self::FOREIGN_KEY)) {
            return;
        }

        DB::statement('ALTER TABLE `homepage_featured_phones` DROP FOREIGN KEY `'.self::FOREIGN_KEY.'`');
    }

    private function foreignKeyExists(string $constraintName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', 'homepage_featured_phones')
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
