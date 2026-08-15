<?php

namespace Tests\Feature;

use App\Models\HomepageSlide;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UrlSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_slide_rejects_a_javascript_link_url(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->editor()->create());

        $this->from(route('homepage-slides.index'))
            ->post('/admin/homepage-slides', [
                'title' => 'Bad link',
                'image' => UploadedFile::fake()->image('slide.png', 40, 40),
                'link_url' => 'javascript:alert(1)',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('link_url');

        $this->assertDatabaseMissing('homepage_slides', ['title' => 'Bad link']);
    }

    public function test_slide_accepts_an_https_link_url(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->editor()->create());

        $this->post('/admin/homepage-slides', [
            'title' => 'Good link',
            'image' => UploadedFile::fake()->image('slide.png', 40, 40),
            'link_url' => 'https://example.com/promo',
            'is_active' => '1',
        ])->assertRedirect(route('homepage-slides.index'));

        $this->assertDatabaseHas('homepage_slides', [
            'title' => 'Good link',
            'link_url' => 'https://example.com/promo',
        ]);
    }

    public function test_slide_api_sanitizes_legacy_unsafe_image_and_link_urls(): void
    {
        config(['app.url' => 'https://catalog.test']);

        HomepageSlide::create([
            'title' => 'Legacy unsafe slide',
            'image_path' => 'https://evil.example/tracker.png',
            'link_url' => 'javascript:alert(1)',
            'is_active' => true,
        ]);

        $this->getJson('/api/homepage-slides?fields=image,linkUrl')
            ->assertOk()
            ->assertExactJson([[
                'image' => asset('assets/phone-placeholder.svg'),
                'linkUrl' => null,
            ]]);
    }

    public function test_imported_official_url_is_sanitized(): void
    {
        $this->actingAs(User::factory()->editor()->create());

        $json = json_encode([
            ['id' => 5001, 'phonename' => 'Danger Phone', 'company' => 'Apple', 'official' => 'javascript:alert(1)'],
            ['id' => 5002, 'phonename' => 'Safe Phone', 'company' => 'Apple', 'official' => 'https://apple.com/iphone'],
        ]);

        $this->post('/admin/products/import', [
            'files' => [UploadedFile::fake()->createWithContent('apple.json', $json)],
            'status' => 'published',
        ])->assertRedirect(route('products.index'));

        $this->assertSame('', Product::query()->where('name', 'Danger Phone')->firstOrFail()->specs['official']);
        $this->assertSame('https://apple.com/iphone', Product::query()->where('name', 'Safe Phone')->firstOrFail()->specs['official']);
    }
}
