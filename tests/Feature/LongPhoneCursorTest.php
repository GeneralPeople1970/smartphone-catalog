<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\ListCursor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LongPhoneCursorTest extends TestCase
{
    use RefreshDatabase;

    public static function maximumNames(): array
    {
        return [
            'Chinese' => [str_repeat('机', 191)],
            'emoji' => [str_repeat('📱', 191)],
        ];
    }

    #[DataProvider('maximumNames')]
    public function test_full_length_names_work_with_generated_and_legacy_escaped_cursors(string $name): void
    {
        $first = Product::factory()->create(['name' => $name, 'specs' => ['saledate' => 20250101]]);
        $next = Product::factory()->create(['name' => $name, 'specs' => ['saledate' => 20250101]]);
        $firstPage = $this->getJson('/api/phones?fields=id&limit=1&paginate=cursor')
            ->assertOk()->assertJsonPath('data.0.id', $first->id);
        $generated = $firstPage->json('meta.nextCursor');
        $legacy = rtrim(strtr(base64_encode(json_encode([
            'f' => 0, 'rd' => 20250101, 'n' => $name, 'id' => $first->id,
        ])), '+/', '-_'), '=');

        foreach ([$generated, $legacy] as $cursor) {
            $this->assertNotNull(ListCursor::decode($cursor));
            $this->getJson('/api/phones?'.http_build_query(['fields' => 'id', 'limit' => 1, 'cursor' => $cursor]))
                ->assertOk()->assertJsonPath('data.0.id', $next->id)
                ->assertJsonCount(1, 'data')->assertJsonPath('meta.hasMore', false);
        }
    }
}
