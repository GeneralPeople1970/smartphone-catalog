<?php

namespace Tests\Feature;

use App\Models\HomepageFeaturedPhone;
use App\Models\HomepageSlide;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_fields_and_aliases_are_preserved(): void
    {
        $product = $this->createProduct();

        $response = $this->getJson('/api/phones?fields=name,brand,displayPrice');

        $response
            ->assertOk()
            ->assertExactJson([[
                'phonename' => $product->name,
                'company' => 'OPPO',
                'displayPrice' => '3999',
            ]]);
    }

    public function test_phone_list_only_returns_published_products(): void
    {
        $published = $this->createProduct();
        $this->createProduct([
            'name' => 'Draft Phone',
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/phones?fields=id,name');

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertExactJson([[
                'id' => $published->id,
                'phonename' => $published->name,
            ]]);
    }

    public function test_invalid_phone_fields_return_the_existing_error_contract(): void
    {
        $response = $this->getJson('/api/phones?fields=unknown');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('invalidFields.0', 'unknown')
            ->assertJsonPath('allowedFields.0', 'id');
    }

    public function test_brand_logo_paths_use_the_frontend_build_directory(): void
    {
        $this->getJson('/api/brands?fields=name,code,displayName,logo')
            ->assertOk()
            ->assertJsonPath('0.name', 'Apple')
            ->assertJsonPath('0.code', 'APPLE')
            ->assertJsonPath('0.displayName', '苹果')
            ->assertJsonPath('0.logo', '/assets/brands/Apple.png');
    }

    public function test_chinese_brand_aliases_resolve_to_english_brands(): void
    {
        $this->createProduct([
            'brand' => 'Xiaomi',
            'name' => 'Xiaomi Alias Phone',
        ]);

        $this->getJson('/api/phones?brand=小米&fields=brand,brandCode')
            ->assertOk()
            ->assertExactJson([[
                'company' => '小米',
                'companyCode' => 'XIAOMI',
            ]]);
    }

    public function test_server_theme_api_has_been_removed(): void
    {
        $this->getJson('/api/site-theme')
            ->assertNotFound();

        $this->assertDatabaseMissing('site_settings', ['key' => 'ui_theme_mode']);
        $this->assertDatabaseMissing('site_settings', ['key' => 'ui_primary_color']);
    }

    public function test_homepage_slide_fields_and_active_filter_are_preserved(): void
    {
        HomepageSlide::query()->delete();

        HomepageSlide::create([
            'title' => 'Active slide',
            'image_path' => '/storage/homepage/active.png',
            'link_url' => '/OPPO',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        HomepageSlide::create([
            'title' => 'Inactive slide',
            'image_path' => '/storage/homepage/inactive.png',
            'link_url' => '/hidden',
            'sort_order' => 20,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/homepage-slides?fields=image_path,link');

        $response
            ->assertOk()
            ->assertExactJson([[
                'image' => '/storage/homepage/active.png',
                'linkUrl' => '/OPPO',
            ]]);
    }

    public function test_homepage_featured_phones_require_active_published_products(): void
    {
        $published = $this->createProduct();
        $draft = $this->createProduct([
            'name' => 'Draft Phone',
            'status' => 'draft',
        ]);

        HomepageFeaturedPhone::create([
            'product_id' => $published->id,
            'title' => 'Recommended',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        HomepageFeaturedPhone::create([
            'product_id' => $draft->id,
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage-featured-phones?fields=name,title');

        $response
            ->assertOk()
            ->assertExactJson([[
                'phonename' => $published->name,
                'recommendTitle' => 'Recommended',
            ]]);
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
