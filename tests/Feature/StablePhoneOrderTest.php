<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\ListCursor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StablePhoneOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_sizes_and_cursor_modes_have_the_same_order(): void
    {
        $dated = [];
        foreach (['Phone X Ultra', 'Phone X', 'Phone X Pro', 'Phone X'] as $name) {
            $dated[] = Product::factory()->create(['name' => $name, 'specs' => ['saledate' => 20250101]])->id;
        }
        $older = Product::factory()->create(['name' => 'Older Phone', 'specs' => ['saledate' => 20200101]]);
        $undated = Product::factory()->create(['name' => 'A undated']);
        $zero = Product::factory()->create(['name' => 'B undated']);
        DB::table('products')->where('id', $zero->id)->update(['release_date' => 0]);
        $expected = [$dated[1], $dated[3], $dated[2], $dated[0], $older->id, $undated->id, $zero->id];
        $this->assertSame($expected, array_column($this->getJson('/api/phones?fields=id&limit=500')->assertOk()->json(), 'id'));
        foreach ([1, 2, 3] as $size) {
            $pageIds = [];
            $cursorIds = [];
            for ($page = 1; $page <= (int) ceil(count($expected) / $size); $page++) {
                $pageIds = [...$pageIds, ...array_column($this->getJson("/api/phones?fields=id&limit=$size&page=$page")->json(), 'id')];
            }
            $cursor = null;
            do {
                $response = $this->getJson('/api/phones?'.http_build_query([
                    'fields' => 'id', 'limit' => $size, 'paginate' => 'cursor', 'cursor' => $cursor,
                ]))->assertOk();
                $cursorIds = [...$cursorIds, ...array_column($response->json('data'), 'id')];
                $cursor = $response->json('meta.nextCursor');
                if ($cursor !== null) {
                    $this->assertNotNull(ListCursor::decode($cursor));
                }
                $this->assertLessThanOrEqual(count($expected), count($cursorIds));
            } while ($cursor !== null);
            $this->assertSame($expected, $pageIds);
            $this->assertSame($expected, $cursorIds);
        }
    }

    public function test_existing_cursor_tokens_still_work(): void
    {
        $first = Product::factory()->create(['name' => 'Phone A', 'specs' => ['saledate' => 20250101]]);
        $next = Product::factory()->create(['name' => 'Phone B', 'specs' => ['saledate' => 20250101]]);
        $token = ListCursor::encode(['f' => 0, 'rd' => 20250101, 'n' => $first->name, 'id' => $first->id]);
        $this->getJson('/api/phones?fields=id&cursor='.$token)
            ->assertOk()->assertJsonPath('data.0.id', $next->id)->assertJsonCount(1, 'data');
    }

    public function test_legacy_undated_cursors_continue_across_null_zero_and_duplicate_names(): void
    {
        $first = Product::factory()->create(['name' => 'Undated']);
        $second = Product::factory()->create(['name' => 'Undated']);
        $third = Product::factory()->create(['name' => 'Undated']);
        DB::table('products')->where('id', $second->id)->update(['release_date' => 0]);
        Product::factory()->create(['name' => 'Dated', 'specs' => ['saledate' => 20250101]]);

        foreach ([$first, $second] as $pivot) {
            $token = ListCursor::encode(['f' => 1, 'rd' => 0, 'n' => $pivot->name, 'id' => $pivot->id]);
            $response = $this->getJson('/api/phones?'.http_build_query(['fields' => 'id', 'cursor' => $token]))
                ->assertOk()->assertHeader('X-Total-Count', 4)->assertHeader('X-Pagination-Mode', 'cursor');

            $this->assertSame($pivot->id === $first->id ? [$second->id, $third->id] : [$third->id], array_column($response->json('data'), 'id'));
            $response->assertJsonPath('meta.total', 4)->assertJsonPath('meta.hasMore', false)->assertJsonPath('meta.nextCursor', null);
        }
    }
}
