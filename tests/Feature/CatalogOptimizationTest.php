<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class CatalogOptimizationTest extends TestCase
{
    // InnoDB FULLTEXT indexes do not expose uncommitted rows. Migration-based
    // isolation keeps these search assertions representative when the MySQL CI
    // job enables CATALOG_SEARCH_DRIVER=fulltext.
    use DatabaseMigrations;

    public function test_brand_phone_counts_are_aggregated_per_brand(): void
    {
        $this->createProduct(['brand' => 'OPPO', 'name' => 'OPPO A']);
        $this->createProduct(['brand' => 'OPPO', 'name' => 'OPPO B']);
        $this->createProduct(['brand' => 'OPPO', 'name' => 'OPPO Draft', 'status' => 'draft']);
        $this->createProduct(['brand' => 'Xiaomi', 'name' => 'Xiaomi A']);

        $counts = collect($this->getJson('/api/brands?fields=code,phoneCount')->assertOk()->json())
            ->keyBy('code');

        $this->assertSame(2, $counts['OPPO']['phoneCount']);
        $this->assertSame(1, $counts['XIAOMI']['phoneCount']);
        $this->assertSame(0, $counts['HUAWEI']['phoneCount']);
    }

    public function test_search_matches_spec_fields_through_search_text(): void
    {
        $this->createProduct([
            'name' => 'Find X9',
            'brand' => 'OPPO',
            'soc_name' => 'Snapdragon 8 Elite',
            'specs' => ['cpu' => 'Snapdragon 8 Elite', 'feature' => '超级闪充', 'saledate' => 20250101],
        ]);
        $this->createProduct(['name' => 'Other Phone', 'brand' => 'Xiaomi']);

        // Feature term lives only in specs.feature -> search_text.
        $this->getJson('/api/search?q=闪充&fields=name')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.phonename', 'Find X9');

        // Chinese alias 骁龙 expands to Snapdragon and matches the SoC text.
        $this->getJson('/api/search?q=骁龙&fields=name')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.phonename', 'Find X9');

        // A non-present SoC alias must not over-match.
        $this->getJson('/api/search?q=联发科&fields=name')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_phone_list_is_ordered_by_release_date_desc(): void
    {
        $this->createProduct(['name' => 'Old Phone', 'specs' => ['saledate' => 20200101]]);
        $this->createProduct(['name' => 'New Phone', 'specs' => ['saledate' => 20250101]]);
        $this->createProduct(['name' => 'Mid Phone', 'specs' => ['saledate' => 20220101]]);
        $this->createProduct(['name' => 'Zzz No Date']);

        $this->getJson('/api/phones?fields=name')
            ->assertOk()
            ->assertJsonPath('0.phonename', 'New Phone')
            ->assertJsonPath('1.phonename', 'Mid Phone')
            ->assertJsonPath('2.phonename', 'Old Phone')
            ->assertJsonPath('3.phonename', 'Zzz No Date');
    }

    public function test_saving_a_product_backfills_the_release_date_column(): void
    {
        $this->createProduct(['name' => 'Dated Phone', 'specs' => ['saledate' => 20240615]]);
        $this->createProduct(['name' => 'Undated Phone', 'specs' => []]);

        $this->assertDatabaseHas('products', ['name' => 'Dated Phone', 'release_date' => 20240615]);
        $this->assertDatabaseHas('products', ['name' => 'Undated Phone', 'release_date' => null]);
    }

    private function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'brand' => 'OPPO',
            'name' => 'Published Phone',
            'image_url' => '/dist/img/phone.png',
            'price' => '3999',
            'soc_name' => 'Test SoC',
            'battery_capacity' => 5000,
            'status' => 'published',
            'specs' => [],
        ], $attributes));
    }
}
