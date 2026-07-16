<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\ListCursor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursorPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function makePhone(string $name, ?int $saledate, string $brand = 'OPPO'): Product
    {
        return Product::create([
            'brand' => $brand,
            'name' => $name,
            'status' => 'published',
            'specs' => $saledate ? ['saledate' => $saledate] : [],
        ]);
    }

    private function seedCatalog(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            // Spread of dated and undated rows, varied names.
            $this->makePhone(
                sprintf('Phone %03d', $i),
                $i % 5 === 0 ? null : 20200000 + $i
            );
        }
    }

    public function test_cursor_mode_walks_the_whole_catalog_without_gaps_or_dupes(): void
    {
        $this->seedCatalog(23);

        $seen = [];
        $cursor = null;
        $pages = 0;

        do {
            $query = ['limit' => 10, 'paginate' => 'cursor', 'fields' => 'id'];
            if ($cursor) {
                $query['cursor'] = $cursor;
            }

            $response = $this->getJson('/api/phones?'.http_build_query($query));
            $response->assertOk();

            foreach ($response->json('data') as $row) {
                $seen[] = $row['id'];
            }

            $cursor = $response->json('meta.nextCursor');
            $pages++;
        } while ($cursor !== null && $pages < 20);

        $this->assertCount(23, $seen);
        $this->assertCount(23, array_unique($seen), 'cursor pages returned duplicates');

        // Same set as the page-mode full read.
        $full = $this->getJson('/api/phones?limit=500&fields=id')->json();
        $this->assertEqualsCanonicalizing(
            array_column($full, 'id'),
            $seen
        );
    }

    public function test_cursor_mode_ordering_matches_page_mode(): void
    {
        $this->seedCatalog(12);

        $pageOrder = array_column($this->getJson('/api/phones?limit=500&fields=id')->json(), 'id');

        $cursorOrder = [];
        $cursor = null;
        do {
            $query = ['limit' => 5, 'paginate' => 'cursor', 'fields' => 'id'];
            if ($cursor) {
                $query['cursor'] = $cursor;
            }
            $response = $this->getJson('/api/phones?'.http_build_query($query));
            $cursorOrder = array_merge($cursorOrder, array_column($response->json('data'), 'id'));
            $cursor = $response->json('meta.nextCursor');
        } while ($cursor !== null);

        $this->assertSame($pageOrder, $cursorOrder);
    }

    public function test_last_page_reports_no_next_cursor(): void
    {
        $this->seedCatalog(3);

        $response = $this->getJson('/api/phones?limit=10&paginate=cursor&fields=id');
        $response->assertOk();

        $response->assertJsonPath('meta.hasMore', false);
        $response->assertJsonPath('meta.nextCursor', null);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_cursor_response_exposes_total_header_and_meta(): void
    {
        $this->seedCatalog(7);

        $response = $this->getJson('/api/phones?limit=3&paginate=cursor&fields=id');

        $response->assertHeader('X-Total-Count', '7');
        $response->assertHeader('X-Pagination-Mode', 'cursor');
        $response->assertJsonPath('meta.total', 7);
        $response->assertJsonPath('meta.hasMore', true);
    }

    public function test_malformed_cursor_returns_422(): void
    {
        $this->seedCatalog(3);

        $this->getJson('/api/phones?paginate=cursor&cursor=not-a-valid-cursor')
            ->assertStatus(422);
    }

    public function test_page_mode_stays_default_and_backwards_compatible(): void
    {
        $this->seedCatalog(3);

        $response = $this->getJson('/api/phones?fields=id');
        $response->assertOk();
        $response->assertHeader('X-Pagination-Mode', 'page');
        // Page mode returns a bare array (unchanged contract).
        $this->assertIsList($response->json());
    }

    public function test_brand_filter_respected_in_cursor_mode(): void
    {
        $this->makePhone('Apple A', 20230101, 'Apple');
        $this->makePhone('OPPO B', 20230102, 'OPPO');

        $response = $this->getJson('/api/phones?brand=Apple&paginate=cursor&fields=id,phonename');
        $response->assertOk();

        $names = array_column($response->json('data'), 'phonename');
        $this->assertSame(['Apple A'], $names);
    }

    public function test_cursor_decode_rejects_garbage(): void
    {
        $this->assertNull(ListCursor::decode(''));
        $this->assertNull(ListCursor::decode('!!!!'));
        $this->assertNull(ListCursor::decode(str_repeat('a', 2000)));
        $this->assertNull(ListCursor::decode(base64_encode('{"f":9,"rd":1,"n":"x","id":1}')));

        $valid = ListCursor::encode(['f' => 0, 'rd' => 20230101, 'n' => 'X', 'id' => 5]);
        $this->assertSame(
            ['f' => 0, 'rd' => 20230101, 'n' => 'X', 'id' => 5],
            ListCursor::decode($valid)
        );
    }
}
