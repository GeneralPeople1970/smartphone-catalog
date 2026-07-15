<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_list_returns_pagination_metadata_headers(): void
    {
        $this->createProduct(['name' => 'New Phone', 'source_key' => 'n', 'specs' => ['saledate' => 20250101]]);
        $this->createProduct(['name' => 'Mid Phone', 'source_key' => 'm', 'specs' => ['saledate' => 20220101]]);
        $this->createProduct(['name' => 'Old Phone', 'source_key' => 'o', 'specs' => ['saledate' => 20200101]]);

        $this->getJson('/api/phones?fields=id&limit=2')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertHeader('X-Total-Count', 3)
            ->assertHeader('X-Per-Page', 2)
            ->assertHeader('X-Current-Page', 1);
    }

    public function test_phone_list_pages_through_results_in_the_database(): void
    {
        $this->createProduct(['name' => 'New Phone', 'source_key' => 'n', 'specs' => ['saledate' => 20250101]]);
        $this->createProduct(['name' => 'Mid Phone', 'source_key' => 'm', 'specs' => ['saledate' => 20220101]]);
        $this->createProduct(['name' => 'Old Phone', 'source_key' => 'o', 'specs' => ['saledate' => 20200101]]);

        // Page 1 keeps the newest-first ordering.
        $this->getJson('/api/phones?fields=name&limit=2&page=1')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.phonename', 'New Phone')
            ->assertJsonPath('1.phonename', 'Mid Phone');

        // Page 2 continues from where page 1 stopped.
        $this->getJson('/api/phones?fields=name&limit=2&page=2')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.phonename', 'Old Phone')
            ->assertHeader('X-Current-Page', 2);

        // A page past the end is an empty list, not an error.
        $this->getJson('/api/phones?fields=name&limit=2&page=3')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_saving_a_product_persists_the_normalized_slug_key(): void
    {
        $this->createProduct(['name' => 'Find X9', 'slug' => 'Find X9']);

        // slug_key is the normalized, dashed, lower-cased form.
        $this->assertDatabaseHas('products', ['name' => 'Find X9', 'slug_key' => 'find-x9']);
    }

    public function test_phone_detail_resolves_a_single_row_by_slug_key(): void
    {
        $product = $this->createProduct(['brand' => 'OPPO', 'name' => 'Find X9', 'slug' => 'find-x9']);

        $this->getJson('/api/phones/detail?slug=find-x9&fields=id,name')
            ->assertOk()
            ->assertJsonPath('id', $product->id)
            ->assertJsonPath('phonename', 'Find X9');
    }

    public function test_phone_detail_slug_lookup_tolerates_case_and_separators(): void
    {
        $this->createProduct(['name' => 'Find X9', 'slug' => 'find-x9']);

        // A link built from a differently-cased/spaced slug still resolves via
        // the shared normalization (old-link compatibility).
        $this->getJson('/api/phones/detail?slug='.rawurlencode('Find X9').'&fields=name')
            ->assertOk()
            ->assertJsonPath('phonename', 'Find X9');
    }

    public function test_phone_detail_honours_the_brand_filter(): void
    {
        $this->createProduct(['brand' => 'OPPO', 'name' => 'Find X9', 'slug' => 'find-x9']);

        $this->getJson('/api/phones/detail?slug=find-x9&brand=OPPO&fields=name')->assertOk();
        $this->getJson('/api/phones/detail?slug=find-x9&brand=Xiaomi&fields=name')->assertNotFound();
    }

    public function test_phone_detail_returns_404_for_an_unknown_slug(): void
    {
        $this->createProduct(['name' => 'Find X9', 'slug' => 'find-x9']);

        $this->getJson('/api/phones/detail?slug=does-not-exist&fields=name')->assertNotFound();
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
