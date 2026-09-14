<?php

namespace Tests\Feature;

use App\Models\HomepageFeaturedPhone;
use App\Models\HomepageSlide;
use App\Models\Product;
use App\Models\User;
use App\Services\HomepageOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_cleanup_preserves_product_and_absolute_slide_references(): void
    {
        Storage::fake('public');
        config(['app.url' => 'https://catalog.test']);
        $path = '/storage/homepage/shared.jpg';
        Storage::disk('public')->put('homepage/shared.jpg', 'image');
        $slide = HomepageSlide::create(['image_path' => $path]);
        $reference = HomepageSlide::create(['image_path' => 'https://catalog.test'.$path.'?v=1']);
        $phone = Product::factory()->create(['image_url' => $path]);
        $this->actingAs(User::factory()->editor()->create())->delete('/admin/homepage-slides/'.$slide->id)->assertRedirect();
        Storage::disk('public')->assertExists('homepage/shared.jpg');
        $this->delete('/admin/homepage-slides/'.$reference->id)->assertRedirect();
        Storage::disk('public')->assertExists('homepage/shared.jpg');
    }

    public function test_homepage_order_is_shared_and_handles_boundaries(): void
    {
        $order = app(HomepageOrder::class);
        foreach ([HomepageSlide::class, HomepageFeaturedPhone::class] as $model) {
            $items = [];
            for ($i = 0; $i < 3; $i++) {
                $items[] = $order->prepend($model, $model === HomepageSlide::class
                    ? ['image_path' => '/assets/logo.png']
                    : ['product_id' => Product::factory()->create()->id]);
            }
            $this->assertFalse($order->move($items[2], -1));
            $this->assertTrue($order->move($items[0], -1));
            $this->assertSame([$items[2]->id, $items[0]->id, $items[1]->id], $model::orderBy('sort_order')->orderBy('id')->pluck('id')->all());
        }
    }

    public function test_failed_edit_does_not_fill_other_rows_or_create_form(): void
    {
        $first = HomepageFeaturedPhone::create(['product_id' => Product::factory()->create()->id, 'title' => 'First']);
        $second = HomepageFeaturedPhone::create(['product_id' => Product::factory()->create()->id, 'title' => 'Second']);
        $this->actingAs(User::factory()->editor()->create())
            ->from('/admin/homepage')->put('/admin/homepage/featured-phones/'.$first->id, [
                '_featured_form' => (string) $first->id, 'title' => 'Changed', 'description' => str_repeat('x', 501),
            ])->assertSessionHasErrors('description');
        $response = $this->get('/admin/homepage')->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'value="Changed"'));
        $response->assertSee('value="Second"', false);
    }

    public function test_picker_does_not_render_the_entire_catalog(): void
    {
        Product::factory()->count(30)->create(['name' => 'Should not be preloaded']);
        $this->actingAs(User::factory()->editor()->create())->get('/admin/homepage')
            ->assertOk()->assertSee('data-product-picker', false)->assertDontSee('Should not be preloaded');
    }

    public function test_checkbox_zero_is_accepted_when_creating_and_editing_homepage_items(): void
    {
        $this->actingAs(User::factory()->editor()->create());
        $this->post('/admin/homepage-slides', ['image_url' => '/assets/logo.png', 'is_active' => '0'])
            ->assertSessionHasNoErrors();
        $slide = HomepageSlide::firstOrFail();
        $this->assertFalse($slide->is_active);
        $this->put('/admin/homepage-slides/'.$slide->id, ['is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($slide->fresh()->is_active);
        $this->put('/admin/homepage-slides/'.$slide->id, ['is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($slide->fresh()->is_active);

        $this->post('/admin/homepage/featured-phones', [
            'product_id' => Product::factory()->create()->id, 'is_active' => '0',
        ])->assertSessionHasNoErrors();
        $featured = HomepageFeaturedPhone::firstOrFail();
        $this->assertFalse($featured->is_active);
        $this->put('/admin/homepage/featured-phones/'.$featured->id, ['is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($featured->fresh()->is_active);
        $this->put('/admin/homepage/featured-phones/'.$featured->id, ['is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($featured->fresh()->is_active);
    }

    public function test_new_unchecked_homepage_items_are_inactive(): void
    {
        $this->actingAs(User::factory()->editor()->create());
        $this->post('/admin/homepage-slides', [
            'image_url' => '/assets/logo.png',
        ])->assertSessionHasNoErrors();
        $this->assertFalse(HomepageSlide::firstOrFail()->is_active);

        $this->post('/admin/homepage/featured-phones', [
            'product_id' => Product::factory()->create()->id,
        ])->assertSessionHasNoErrors();
        $this->assertFalse(HomepageFeaturedPhone::firstOrFail()->is_active);
    }

    public function test_validation_assigns_the_form_identity_from_the_route(): void
    {
        $this->actingAs(User::factory()->editor()->create());
        $first = HomepageSlide::create(['title' => 'First', 'image_path' => '/assets/logo.png', 'is_active' => true]);
        $second = HomepageSlide::create(['title' => 'Second', 'image_path' => '/assets/logo.png', 'is_active' => true]);

        $this->from('/admin/homepage-slides')->put('/admin/homepage-slides/'.$first->id, [
            '_slide_form' => (string) $second->id, 'title' => 'Unsaved title', 'image_url' => 'javascript:bad',
        ])->assertSessionHasErrors('image_url');
        $response = $this->get('/admin/homepage-slides')->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());

        $this->assertSame('Unsaved title', $document->getElementById('title-'.$first->id)->getAttribute('value'));
        $this->assertSame('Second', $document->getElementById('title-'.$second->id)->getAttribute('value'));
        $this->assertSame('', $document->getElementById('title')->getAttribute('value'));
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//form[@id="slide-update-'.$first->id.'"]//input[@type="checkbox" and @checked]')->length);
        $this->assertSame(1, $xpath->query('//form[@id="slide-update-'.$second->id.'"]//input[@type="checkbox" and @checked]')->length);
    }

    public function test_brand_normalization_reports_conflicts_without_overwriting_them(): void
    {
        $alias = Product::factory()->create(['brand' => '小米']);
        $conflict = Product::factory()->create(['brand' => '苹果', 'source_file' => 'Xiaomi.json']);
        $this->artisan('catalog:normalize-brands')->assertSuccessful();
        $this->assertSame('小米', $alias->fresh()->brand);
        $this->artisan('catalog:normalize-brands --apply')->assertSuccessful();
        $this->assertSame('Xiaomi', $alias->fresh()->brand);
        $this->assertSame('苹果', $conflict->fresh()->brand);
        $this->artisan('catalog:normalize-brands --apply')->expectsOutput('Updated 0 aliases.')->assertSuccessful();
    }
}
