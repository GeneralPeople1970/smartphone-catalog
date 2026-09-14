<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->editor()->create());
    }

    public static function invalidSpecs(): array
    {
        return array_map(fn ($value) => [$value], [
            'true', '42', '"text"', 'null', '[]',
            '{"official":[]}', '{"feature":{}}', '{"saledate":[]}',
        ]);
    }

    #[DataProvider('invalidSpecs')]
    public function test_invalid_spec_shapes_are_validation_errors(string $json): void
    {
        $this->post('/admin/products', [
            'brand' => 'Apple', 'name' => 'Test Phone', 'status' => 'draft',
            'specs_text' => $json,
        ])->assertSessionHasErrors('specs_text');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_extended_specs_are_preserved_and_basic_fields_are_authoritative(): void
    {
        $this->post('/admin/products', [
            'brand' => '小米', 'name' => 'Xiaomi Phone', 'status' => 'draft',
            'price' => '3999 起', 'battery_capacity' => 5000,
            'specs_text' => '{"phonename":"Old","camera":{"lenses":[1,2]},"feature":"Test"}',
        ])->assertSessionHasNoErrors();

        $phone = Product::firstOrFail();
        $this->assertSame('Xiaomi', $phone->brand);
        $this->assertSame('Xiaomi Phone', $phone->specs['phonename']);
        $this->assertSame(['lenses' => [1, 2]], $phone->specs['camera']);
        $this->assertSame($phone->id, $phone->specs['id']);
    }

    public static function invalidImports(): array
    {
        return [
            'malformed id' => [['id' => '7abc'], 'id'],
            'fractional id' => [['id' => 7.5], 'id'],
            'array name' => [['id' => 7, 'phonename' => ['bad']], 'phonename'],
            'long name' => [['id' => 7, 'phonename' => str_repeat('x', 192)], 'name'],
            'battery overflow' => [['id' => 7, 'battery' => 40000], 'battery_capacity'],
            'invalid battery' => [['id' => 7, 'battery' => 'not a battery'], 'battery'],
        ];
    }

    #[DataProvider('invalidImports')]
    public function test_invalid_import_fields_roll_back_the_batch(array $bad, string $field): void
    {
        $response = $this->post('/admin/products/import', [
            'files' => [UploadedFile::fake()->createWithContent('Apple.json', json_encode([
                ['id' => 9001, 'phonename' => 'Valid Phone', 'company' => 'Apple'],
                $bad,
            ]))],
            'status' => 'published',
        ])->assertSessionHasErrors('files');

        $this->assertStringContainsString('Apple.json', session('errors')->first('files'));
        $this->assertStringContainsString($field, session('errors')->first('files'));
        $this->assertDatabaseCount('products', 0);
    }

    public function test_edited_brand_wins_over_import_provenance_everywhere(): void
    {
        $phone = Product::factory()->create(['brand' => 'Apple', 'source_file' => 'Apple.json']);
        $this->put('/admin/products/'.$phone->id, [
            'brand' => 'Xiaomi', 'name' => $phone->name, 'status' => 'published',
        ])->assertSessionHasNoErrors();

        $this->getJson('/api/phones/'.$phone->id.'?fields=companyCode')
            ->assertJsonPath('companyCode', 'XIAOMI');
        $this->getJson('/api/phones?brand=Apple')->assertJsonCount(0);
        $this->getJson('/api/phones?brand=Xiaomi')->assertJsonCount(1);
        $brands = collect($this->getJson('/api/brands')->json())->keyBy('code');
        $this->assertSame(0, $brands['APPLE']['phoneCount']);
        $this->assertSame(1, $brands['XIAOMI']['phoneCount']);
    }
}
