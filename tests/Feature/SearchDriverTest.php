<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchDriverTest extends TestCase
{
    use RefreshDatabase;

    private function seedPhones(): void
    {
        Product::create([
            'brand' => 'Xiaomi',
            'name' => '小米 15 Pro',
            'status' => 'published',
            'specs' => ['socname' => '骁龙 8 Gen 4', 'feature' => '120W 闪充'],
        ]);
        Product::create([
            'brand' => 'Apple',
            'name' => 'iPhone 17',
            'status' => 'published',
            'specs' => ['socname' => 'A19'],
        ]);
    }

    public function test_like_driver_finds_chinese_and_alias_keywords(): void
    {
        config(['catalog.search.driver' => 'like']);
        $this->seedPhones();

        $this->getJson('/api/phones?q='.rawurlencode('骁龙').'&fields=phonename')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.phonename', '小米 15 Pro');

        $this->getJson('/api/phones?q='.rawurlencode('闪充').'&fields=phonename')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_fulltext_driver_falls_back_to_like_on_sqlite(): void
    {
        // The tests run on SQLite; with the driver set to fulltext the scope
        // must transparently degrade to LIKE so search never breaks on a
        // non-MySQL connection.
        config(['catalog.search.driver' => 'fulltext']);
        $this->seedPhones();

        $this->getJson('/api/phones?q='.rawurlencode('骁龙').'&fields=phonename')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.phonename', '小米 15 Pro');

        $this->getJson('/api/phones?q=iphone&fields=phonename')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.phonename', 'iPhone 17');
    }
}
