<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class SearchDriverTest extends TestCase
{
    // InnoDB FULLTEXT indexes do not expose uncommitted rows. Migration-based
    // isolation lets the MySQL job exercise the real fulltext path while
    // retaining per-test database isolation.
    use DatabaseMigrations;

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

    public function test_fulltext_driver_matches_keywords_on_mysql_and_falls_back_on_sqlite(): void
    {
        // SQLite must transparently degrade to LIKE; the MySQL CI job executes
        // the same assertions through its ngram FULLTEXT index.
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
