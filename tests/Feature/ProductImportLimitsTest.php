<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->editor()->create());
    }

    public function test_import_rejects_a_non_array_root(): void
    {
        $this->from(route('products.import'))
            ->post('/admin/products/import', [
                'files' => [UploadedFile::fake()->createWithContent('bad.json', json_encode(['id' => 1]))],
                'status' => 'published',
            ])
            ->assertSessionHasErrors('files');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_import_rejects_an_oversized_string_field(): void
    {
        $json = json_encode([
            ['id' => 1, 'phonename' => 'Huge', 'company' => 'Apple', 'feature' => str_repeat('a', 6000)],
        ]);

        $this->from(route('products.import'))
            ->post('/admin/products/import', [
                'files' => [UploadedFile::fake()->createWithContent('huge.json', $json)],
                'status' => 'published',
            ])
            ->assertSessionHasErrors('files');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_import_rejects_too_many_files(): void
    {
        $files = [];
        for ($i = 0; $i < 21; $i++) {
            $files[] = UploadedFile::fake()->createWithContent("file{$i}.json", json_encode([['id' => $i + 1]]));
        }

        $this->from(route('products.import'))
            ->post('/admin/products/import', [
                'files' => $files,
                'status' => 'published',
            ])
            ->assertSessionHasErrors('files');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_import_rejects_more_records_than_the_cap(): void
    {
        $records = [];
        for ($i = 1; $i <= 2001; $i++) {
            $records[] = ['id' => $i, 'phonename' => "P{$i}", 'company' => 'Apple'];
        }

        $this->from(route('products.import'))
            ->post('/admin/products/import', [
                'files' => [UploadedFile::fake()->createWithContent('many.json', json_encode($records))],
                'status' => 'published',
            ])
            ->assertSessionHasErrors('files');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_import_caps_total_records_across_files(): void
    {
        $file = fn (int $start) => UploadedFile::fake()->createWithContent(
            "f{$start}.json",
            json_encode(array_map(fn (int $i) => ['id' => $i, 'phonename' => "P{$i}", 'company' => 'Apple'], range($start, $start + 1499)))
        );

        // Two files of 1500 valid records each (each within the per-file cap)
        // but 3000 total exceeds the batch cap.
        $this->from(route('products.import'))
            ->post('/admin/products/import', [
                'files' => [$file(1), $file(2000)],
                'status' => 'published',
            ])
            ->assertSessionHasErrors('files');

        $this->assertSame(0, Product::query()->count());
    }
}
