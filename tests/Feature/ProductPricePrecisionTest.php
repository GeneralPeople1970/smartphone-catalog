<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductPricePrecisionTest extends TestCase
{
    use RefreshDatabase;

    public static function textPrices(): array
    {
        return array_map(fn (string $price) => [$price], [
            '1.0e999', '9223372036854775808', '9999999999999999999999999999',
            '0.12345678901234567890123456789', '3999 起',
        ]);
    }

    #[DataProvider('textPrices')]
    public function test_form_preserves_prices_that_cannot_be_losslessly_represented_as_numbers(string $price): void
    {
        $this->actingAs(User::factory()->editor()->create())->post('/admin/products', [
            'brand' => 'Apple', 'name' => 'Text Price Phone', 'status' => 'published', 'price' => $price,
        ])->assertSessionHasNoErrors()->assertRedirect(route('products.index'));

        $this->assertPriceIsPreserved(Product::firstOrFail(), $price);
    }

    #[DataProvider('textPrices')]
    public function test_import_preserves_prices_that_cannot_be_losslessly_represented_as_numbers(string $price): void
    {
        $this->actingAs(User::factory()->editor()->create())->post('/admin/products/import', [
            'files' => [UploadedFile::fake()->createWithContent('Apple.json', json_encode([
                ['id' => 9001, 'phonename' => 'Text Price Phone', 'price' => $price],
            ]))],
            'status' => 'published',
        ])->assertSessionHasNoErrors()->assertRedirect(route('products.index'));

        $this->assertPriceIsPreserved(Product::firstOrFail(), $price);
    }

    public function test_ordinary_numeric_prices_keep_the_existing_api_and_spec_types(): void
    {
        $this->actingAs(User::factory()->editor()->create())->post('/admin/products/import', [
            'files' => [UploadedFile::fake()->createWithContent('Apple.json', json_encode([
                ['id' => 9001, 'phonename' => 'Integer Price', 'price' => 3999],
                ['id' => 9002, 'phonename' => 'Decimal Price', 'price' => 3999.5],
            ]))],
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $this->assertSame(3999, Product::findOrFail(9001)->specs['price']);
        $this->assertSame(3999.5, Product::findOrFail(9002)->specs['price']);
        $this->getJson('/api/phones/9001?fields=price')->assertOk()->assertJsonPath('price', 3999);
        $this->getJson('/api/phones/9002?fields=price')->assertOk()->assertJsonPath('price', '3999.5');
    }

    private function assertPriceIsPreserved(Product $phone, string $price): void
    {
        $this->assertSame($price, $phone->price);
        $this->assertSame($price, $phone->specs['price']);
        $this->getJson('/api/phones/'.$phone->id.'?fields=price,displayPrice')
            ->assertOk()->assertJsonPath('price', $price)->assertJsonPath('displayPrice', $price);
    }
}
