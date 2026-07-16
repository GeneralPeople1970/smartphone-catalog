<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiMalformedParamsTest extends TestCase
{
    use RefreshDatabase;

    private function makePhone(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'brand' => 'OPPO',
            'name' => 'Published Phone '.uniqid(),
            'status' => 'published',
            'specs' => [],
        ], $attributes));
    }

    // --- array/object where a string is expected -----------------------------

    public function test_array_brand_returns_422(): void
    {
        $this->getJson('/api/phones?brand[]=OPPO')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_array_q_returns_422(): void
    {
        $this->getJson('/api/phones?q[]=x')->assertStatus(422);
        $this->getJson('/api/search?q[]=x')->assertStatus(422);
    }

    public function test_object_shaped_q_returns_422(): void
    {
        $this->getJson('/api/phones?q[foo]=bar')->assertStatus(422);
    }

    public function test_array_slug_returns_422(): void
    {
        $this->getJson('/api/phones/detail?slug[]=x')->assertStatus(422);
    }

    public function test_array_page_and_limit_return_422(): void
    {
        $this->getJson('/api/phones?page[]=1')->assertStatus(422);
        $this->getJson('/api/phones?limit[]=10')->assertStatus(422);
    }

    public function test_nested_array_ids_and_fields_do_not_crash(): void
    {
        $this->makePhone();

        // Nested arrays are rejected with 422 by the malformed-array rule.
        $this->getJson('/api/phones?ids[][]=1')->assertStatus(422);
        $this->getJson('/api/phones?fields[][]=id')->assertStatus(422);
    }

    public function test_flat_array_ids_and_fields_remain_accepted(): void
    {
        $phone = $this->makePhone();

        $this->getJson('/api/phones?ids[]='.$phone->id)->assertOk();
        $this->getJson('/api/phones?fields[]=id&fields[]=phonename')->assertOk();
    }

    // --- integer boundaries ---------------------------------------------------

    public function test_huge_page_number_returns_422_not_500(): void
    {
        $this->makePhone();

        $this->getJson('/api/phones?page=9223372036854775807&limit=500')
            ->assertStatus(422);
    }

    public function test_page_beyond_int64_returns_422(): void
    {
        $this->getJson('/api/phones?page=99999999999999999999999999')->assertStatus(422);
    }

    public function test_page_beyond_total_pages_returns_empty_list(): void
    {
        $this->makePhone();

        $response = $this->getJson('/api/phones?page=99999&limit=500');

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_non_integer_page_and_limit_return_422(): void
    {
        $this->getJson('/api/phones?page=abc')->assertStatus(422);
        $this->getJson('/api/phones?limit=abc')->assertStatus(422);
        $this->getJson('/api/phones?page=1.5')->assertStatus(422);
    }

    public function test_limit_below_one_returns_422_and_large_limit_is_capped(): void
    {
        $this->makePhone();

        $this->getJson('/api/phones?limit=0')
            ->assertStatus(422)
            ->assertJsonPath('message', 'limit 至少为 1。');
        $this->getJson('/api/phones?limit=-1')->assertStatus(422);

        // Above the cap stays accepted (silently clamped) for compatibility.
        $this->getJson('/api/phones?limit=99999')
            ->assertOk()
            ->assertHeader('X-Per-Page', '500');
    }

    // --- other endpoints / regressions ---------------------------------------

    public function test_brand_search_with_array_q_returns_422(): void
    {
        $this->getJson('/api/brands/Apple/search?q[]=x')->assertStatus(422);
    }

    public function test_invalid_fields_still_return_unified_422(): void
    {
        $this->getJson('/api/phones?fields=nope')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'invalidFields', 'allowedFields']);
    }

    public function test_valid_requests_are_unaffected(): void
    {
        $phone = $this->makePhone();

        $this->getJson('/api/phones')->assertOk();
        $this->getJson('/api/phones?page=1&limit=5')->assertOk();
        $this->getJson('/api/phones/'.$phone->id)->assertOk();
    }
}
