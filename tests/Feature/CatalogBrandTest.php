<?php

namespace Tests\Feature;

use App\Models\HomepageFeaturedPhone;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CatalogBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_unrecognized_stored_brands_are_not_reassigned_from_provenance(): void
    {
        $phone = Product::factory()->create(['brand' => 'Independent', 'source_file' => 'Apple.json']);

        $this->getJson('/api/phones/'.$phone->id.'?fields=company,companyCode')
            ->assertOk()->assertExactJson(['company' => 'Independent', 'companyCode' => 'INDEPENDENT']);
        $this->getJson('/api/phones?brand=Apple')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/phones?brand=Independent')->assertOk()->assertJsonCount(1);
        $brands = collect($this->getJson('/api/brands')->assertOk()->json())->keyBy('code');
        $this->assertSame(0, $brands['APPLE']['phoneCount']);
    }

    public function test_brand_alias_filters_match_case_and_whitespace_normalization_in_mapping(): void
    {
        $phone = Product::factory()->create(['brand' => ' aPpLe ', 'source_file' => 'Xiaomi.json', 'slug' => 'apple-alias']);

        foreach (['APPLE', '苹果', 'iPhone'] as $brand) {
            $this->getJson('/api/phones?'.http_build_query(['brand' => $brand, 'fields' => 'id,companyCode']))
                ->assertOk()->assertJsonCount(1)->assertJsonPath('0.companyCode', 'APPLE');
        }
        $this->getJson('/api/phones/detail?slug=apple-alias&brand=Apple&fields=id')
            ->assertOk()->assertJsonPath('id', $phone->id);
        $this->getJson('/api/phones?brand=Xiaomi')->assertOk()->assertJsonCount(0);
        $brands = collect($this->getJson('/api/brands')->assertOk()->json())->keyBy('code');
        $this->assertSame(1, $brands['APPLE']['phoneCount']);
        $this->assertSame(0, $brands['XIAOMI']['phoneCount']);
    }

    public function test_database_collation_does_not_merge_an_unknown_brand_into_a_confirmed_brand(): void
    {
        Product::factory()->create(['brand' => 'Apple', 'name' => 'Known Brand']);
        Product::factory()->create(['brand' => 'Ápple', 'name' => 'Unrecognized Brand']);

        $this->getJson('/api/phones?brand=Apple&fields=phonename')
            ->assertOk()->assertExactJson([['phonename' => 'Known Brand']]);
        $brands = collect($this->getJson('/api/brands')->assertOk()->json())->keyBy('code');
        $this->assertSame(1, $brands['APPLE']['phoneCount']);
    }

    public function test_import_infers_missing_brands_but_keeps_explicit_brands_and_original_provenance(): void
    {
        $this->actingAs(User::factory()->editor()->create())->post('/admin/products/import', [
            'files' => [UploadedFile::fake()->createWithContent('Apple.json', json_encode([
                ['id' => 9001, 'phonename' => 'Inferred Phone'],
                ['id' => 9002, 'phonename' => 'Legacy Label', 'company' => 'Unrecognized Source'],
                ['id' => 9003, 'phonename' => 'Explicit Phone', 'company' => '小米'],
            ]))],
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Apple', Product::findOrFail(9001)->brand);
        $this->assertSame('Apple', Product::findOrFail(9002)->brand);
        $this->assertSame('Unrecognized Source', Product::findOrFail(9002)->specs['source_company']);
        $this->assertSame('Xiaomi', Product::findOrFail(9003)->brand);
        $this->assertSame('Apple.json', Product::findOrFail(9003)->source_file);
        $this->assertSame('小米', Product::findOrFail(9003)->specs['source_company']);
    }

    public function test_brand_audit_preserves_unknown_and_conflicting_rows_and_only_applies_confirmed_aliases(): void
    {
        $alias = Product::factory()->create(['brand' => 'xIaOmI', 'source_file' => 'xiaomi.JSON']);
        $conflict = Product::factory()->create(['brand' => '苹果', 'source_file' => 'xiaomi.JSON']);
        $unknown = Product::factory()->create(['brand' => 'Unknown', 'source_file' => 'Apple.json']);

        $this->artisan('catalog:normalize-brands')->expectsOutput('Report only. No data changed.')->assertSuccessful();
        $this->assertSame('xIaOmI', $alias->fresh()->brand);

        $this->artisan('catalog:normalize-brands --apply')->expectsOutput('Updated 1 aliases.')->assertSuccessful();
        $this->assertSame('Xiaomi', $alias->fresh()->brand);
        $this->assertSame('xIaOmI', $alias->fresh()->specs['source_company']);
        $this->assertSame('苹果', $conflict->fresh()->brand);
        $this->assertSame('Unknown', $unknown->fresh()->brand);
    }

    public function test_list_search_detail_and_recommendations_share_text_prices_and_brand_mapping(): void
    {
        $phone = Product::factory()->create([
            'brand' => 'Xiaomi', 'source_file' => 'Apple.json', 'price' => '3999 起',
            'slug' => 'shared-phone', 'specs' => ['feature' => 'Text price', 'saledate' => 20250101],
        ]);
        HomepageFeaturedPhone::create(['product_id' => $phone->id, 'is_active' => true]);
        $query = http_build_query(['fields' => 'id,name,brand,brandCode,price,displayPrice,image,releaseDate,feature,slug']);

        $list = $this->getJson('/api/phones?'.$query)->assertOk()->json('0');
        $search = $this->getJson('/api/search?q='.$phone->id.'&'.$query)->assertOk()->json('0');
        $detail = $this->getJson('/api/phones/'.$phone->id.'?'.$query)->assertOk()->json();
        $featured = $this->getJson('/api/homepage-featured-phones?'.$query)->assertOk()->json('0');

        $this->assertSame('XIAOMI', $list['companyCode']);
        $this->assertSame('3999 起', $list['price']);
        $this->assertSame('3999 起', $list['displayPrice']);
        $this->assertSame($list, $search);
        $this->assertSame($list, $detail);
        $this->assertSame($list, $featured);
    }

    public function test_recommendation_description_uses_the_same_safe_legacy_spec_mapping(): void
    {
        $phone = Product::factory()->create(['specs' => ['feature' => ['unexpected']]]);
        HomepageFeaturedPhone::create(['product_id' => $phone->id, 'is_active' => true]);

        $this->getJson('/api/homepage-featured-phones?fields=feature,recommendDescription')
            ->assertOk()->assertExactJson([['feature' => '', 'recommendDescription' => '']]);
    }
}
