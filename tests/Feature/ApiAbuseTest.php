<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAbuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_api_routes_are_throttled(): void
    {
        foreach (['/api/brands', '/api/me', '/api/phones'] as $route) {
            $this->getJson($route)
                ->assertOk()
                ->assertHeader('X-RateLimit-Limit', 120);
        }
    }

    public function test_phone_limit_is_defaulted_and_capped(): void
    {
        $this->createProduct(['name' => 'Phone A', 'source_key' => 'a']);
        $this->createProduct(['name' => 'Phone B', 'source_key' => 'b']);

        // No limit -> all results (within the default cap).
        $this->getJson('/api/phones?fields=id')->assertOk()->assertJsonCount(2);

        // Explicit limit is honoured.
        $this->getJson('/api/phones?fields=id&limit=1')->assertOk()->assertJsonCount(1);

        // A limit over the maximum is capped, not an error.
        $this->getJson('/api/phones?fields=id&limit=100000')->assertOk()->assertJsonCount(2);

        // limit < 1 keeps the existing 422 contract.
        $this->getJson('/api/phones?fields=id&limit=0')
            ->assertStatus(422)
            ->assertJsonPath('message', 'limit 至少为 1。');
    }

    public function test_an_overlong_search_keyword_is_truncated_not_rejected(): void
    {
        $this->getJson('/api/phones?fields=id&q='.str_repeat('a', 300))
            ->assertOk();
    }

    private function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'brand' => 'OPPO',
            'name' => 'Published Phone',
            'status' => 'published',
            'specs' => [],
        ], $attributes));
    }
}
