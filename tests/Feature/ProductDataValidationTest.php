<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductDataValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->editor()->create());
    }

    public static function invalidKnownSpecs(): array
    {
        return [
            'numeric brand' => ['{"company":42}', 'company'],
            'numeric model name' => ['{"phonename":42}', 'phonename'],
            'numeric image' => ['{"imgurl":42}', 'imgurl'],
            'numeric processor' => ['{"socname":42}', 'socname'],
            'numeric official link' => ['{"official":42}', 'official'],
            'malformed date' => ['{"saledate":"20250101oops"}', 'saledate'],
            'boolean date' => ['{"saledate":true}', 'saledate'],
            'fractional date' => ['{"saledate":20250101.5}', 'saledate'],
            'overflowing date' => ['{"saledate":4294967296}', 'saledate'],
            'fractional battery' => ['{"battery":5000.5}', 'battery'],
            'boolean id' => ['{"id":true}', 'id'],
            'malformed id' => ['{"id":"2oops"}', 'id'],
            'long model name' => [json_encode(['phonename' => str_repeat('x', 192)]), 'phonename'],
            'long image' => [json_encode(['imgurl' => str_repeat('x', 2049)]), 'imgurl'],
            'long price' => [json_encode(['price' => str_repeat('x', 101)]), 'price'],
            'unrepresentable extension number' => ['{"extension":{"number":1e400}}', 'extension.number'],
        ];
    }

    #[DataProvider('invalidKnownSpecs')]
    public function test_form_validates_known_specs_before_syncing_basic_fields(string $json, string $field): void
    {
        $this->post('/admin/products', [
            'brand' => 'Apple', 'name' => 'Valid Phone', 'status' => 'draft',
            'specs_text' => $json,
        ])->assertSessionHasErrors('specs_text');

        $this->assertStringContainsString($field, session('errors')->first('specs_text'));
        $this->assertDatabaseCount('products', 0);
    }

    public function test_form_does_not_coerce_numeric_images_or_processor_names_to_text(): void
    {
        $this->post('/admin/products', [
            'brand' => 'Apple', 'name' => 'Valid Phone', 'status' => 'draft',
            'image_url' => 42, 'soc_name' => 42,
        ])->assertSessionHasErrors(['image_url', 'soc_name']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_object_and_array_extensions_survive_create_and_edit_round_trips(): void
    {
        $this->post('/admin/products', [
            'brand' => 'Apple', 'name' => 'Extended Phone', 'status' => 'published',
            'specs_text' => '{"extension":{"options":{},"items":[],"enabled":true,"value":null}}',
        ])->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $stored = json_decode($product->getRawOriginal('specs'))->extension;
        $this->assertInstanceOf(\stdClass::class, $stored->options);
        $this->assertSame([], $stored->items);
        $this->assertTrue($stored->enabled);
        $this->assertNull($stored->value);

        $this->put('/admin/products/'.$product->id, [
            'brand' => 'Apple', 'name' => 'Edited Phone', 'status' => 'published',
            'specs_text' => json_encode($product->specsForEditing()),
        ])->assertSessionHasNoErrors();

        $stored = json_decode($product->fresh()->getRawOriginal('specs'))->extension;
        $this->assertInstanceOf(\stdClass::class, $stored->options);
        $this->assertSame([], $stored->items);
    }

    public function test_editing_basic_fields_without_parameter_json_keeps_existing_extensions(): void
    {
        $phone = Product::factory()->create(['specs' => ['extension' => ['version' => 2, 'enabled' => true]]]);
        $this->put('/admin/products/'.$phone->id, [
            'brand' => 'Xiaomi', 'name' => 'Edited Phone', 'status' => 'published',
        ])->assertSessionHasNoErrors();

        $specs = $phone->fresh()->specs;
        $this->assertSame(2, $specs['extension']['version']);
        $this->assertTrue($specs['extension']['enabled']);
        $this->assertSame('Xiaomi', $specs['company']);
    }

    public static function invalidImportFields(): array
    {
        return [
            'boolean id' => [['id' => true], 'id'],
            'floating point id' => [['id' => 7.0], 'id'],
            'numeric company' => [['company' => 42], 'company'],
            'numeric image alias' => [['image' => 42], 'image'],
            'numeric processor alias' => [['processor' => 42], 'processor'],
            'fractional battery' => [['battery' => 5000.5], 'battery'],
            'fractional date' => [['saledate' => 20250101.5], 'saledate'],
            'overflowing date' => [['saledate' => 4294967296], 'saledate'],
            'long price' => [['price' => str_repeat('x', 101)], 'price'],
            'invalid unused alias' => [['phonename' => 'Valid Name', 'name' => []], 'name'],
            'blank original model name' => [['phonename' => '   '], 'phonename'],
        ];
    }

    #[DataProvider('invalidImportFields')]
    public function test_import_errors_identify_filename_record_and_original_field(array $invalid, string $field): void
    {
        $this->post('/admin/products/import', [
            'files' => [UploadedFile::fake()->createWithContent('Apple.json', json_encode([
                ['id' => 9001, 'phonename' => 'Valid Phone'],
                [...['id' => 9002, 'phonename' => 'Invalid Phone'], ...$invalid],
            ], JSON_PRESERVE_ZERO_FRACTION))],
            'status' => 'published',
        ])->assertSessionHasErrors('files');

        $message = session('errors')->first('files');
        $this->assertStringContainsString('Apple.json 第 2 条', $message);
        $this->assertStringContainsString($field, $message);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_numeric_prices_and_legacy_battery_units_are_supported_without_losing_extensions(): void
    {
        $this->post('/admin/products/import', [
            'files' => [UploadedFile::fake()->createWithContent('Apple.json', '[{"id":"9001","phonename":"Valid Phone","battery":"5000 mAh","price":3999.5,"saledate":"20250101","extra":{"options":{}}}]')],
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertSame(5000, $product->battery_capacity);
        $this->assertSame('3999.5', $product->price);
        $this->assertSame(20250101, $product->release_date);
        $this->assertInstanceOf(\stdClass::class, json_decode($product->getRawOriginal('specs'))->extra->options);
    }

    public function test_import_rolls_back_prior_writes_when_a_database_conflict_occurs(): void
    {
        $this->app->instance(ProductWriter::class, new class extends ProductWriter
        {
            public function save(array $data, ?Product $product = null): Product
            {
                if ($data['id'] === 9002) {
                    DB::table('products')->insert([
                        'id' => 9002, 'brand' => 'Apple', 'name' => 'Concurrent insert', 'status' => 'draft',
                    ]);
                }

                return parent::save($data, $product);
            }
        });

        $this->post('/admin/products/import', [
            'files' => [UploadedFile::fake()->createWithContent('Apple.json', json_encode([
                ['id' => 9001, 'phonename' => 'First Phone'],
                ['id' => 9002, 'phonename' => 'Second Phone'],
            ]))],
            'status' => 'published',
        ])->assertSessionHasErrors('files');

        $this->assertStringContainsString('Apple.json 第 2 条', session('errors')->first('files'));
        $this->assertStringContainsString('id', session('errors')->first('files'));
        $this->assertDatabaseCount('products', 0);
    }
}
