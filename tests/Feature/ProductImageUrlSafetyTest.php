<?php

namespace Tests\Feature;

use App\Models\HomepageFeaturedPhone;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductImageUrlSafetyTest extends TestCase
{
    use RefreshDatabase;

    private string $placeholder;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://catalog.test']);
        $this->placeholder = asset('assets/phone-placeholder.svg');
    }

    public static function dangerousUrls(): array
    {
        $bs = '\\'; // single backslash

        return [
            'backslash protocol-relative' => ['/'.$bs.'evil.example/image.png'],
            'double backslash host' => [$bs.$bs.'evil.example/image.png'],
            'leading backslash' => [$bs.'evil.example/image.png'],
            'backslash scheme' => ['https:'.$bs.$bs.'evil.example/x.png'],
            'protocol-relative' => ['//evil.example/image.png'],
            'javascript scheme' => ['javascript:alert(1)'],
            'data html' => ['data:text/html;base64,PHNjcmlwdD4='],
            'control char' => ["/img/a\tb.png"],
            'offsite http' => ['https://evil.example/image.png'],
        ];
    }

    #[DataProvider('dangerousUrls')]
    public function test_dangerous_image_urls_fall_back_to_placeholder(string $url): void
    {
        $this->assertSame($this->placeholder, Product::safeImageUrl($url));
    }

    public function test_safe_image_urls_are_preserved(): void
    {
        $this->assertSame('/assets/brands/apple.png', Product::safeImageUrl('/assets/brands/apple.png'));
        $this->assertSame('/storage/homepage/a.webp', Product::safeImageUrl('/storage/homepage/a.webp'));
        $this->assertSame('https://catalog.test/x.png', Product::safeImageUrl('https://catalog.test/x.png'));
        $this->assertSame(asset('img/a.png'), Product::safeImageUrl('img/a.png'));
    }

    public function test_http_images_are_rejected_on_https_pages_but_remain_usable_in_http_development(): void
    {
        $httpImage = 'http://catalog.test/x.png';

        $this->assertSame($this->placeholder, Product::safeImageUrl($httpImage));

        config(['app.url' => 'http://catalog.test']);

        $this->assertSame($httpImage, Product::safeImageUrl($httpImage));
    }

    public function test_empty_image_url_falls_back(): void
    {
        $this->assertSame($this->placeholder, Product::safeImageUrl(''));
        $this->assertSame($this->placeholder, Product::safeImageUrl(null));
    }

    public function test_public_phone_apis_do_not_expose_unsafe_image_or_official_urls(): void
    {
        $product = Product::create([
            'brand' => 'Apple',
            'name' => 'Unsafe URL Phone',
            'image_url' => 'https://evil.example/tracker.png',
            'status' => 'published',
            'specs' => ['official' => 'javascript:alert(1)'],
        ]);

        HomepageFeaturedPhone::create([
            'product_id' => $product->id,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $expected = [
            'imgurl' => $this->placeholder,
            'official' => '',
        ];

        $this->getJson('/api/phones?fields=imgurl,official')
            ->assertOk()
            ->assertExactJson([$expected]);

        $this->getJson('/api/phones/'.$product->id.'?fields=imgurl,official')
            ->assertOk()
            ->assertExactJson($expected);

        $this->getJson('/api/homepage-featured-phones?fields=imgurl')
            ->assertOk()
            ->assertExactJson([['imgurl' => $this->placeholder]]);
    }
}
