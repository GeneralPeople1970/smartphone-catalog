<?php

namespace Tests\Feature;

use App\Models\HomepageSlide;
use App\Models\Product;
use App\Services\ManagedImage;
use App\Support\ImageUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManagedImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['app.url' => 'https://catalog.test']);
    }

    public function test_an_explicit_default_port_still_counts_as_a_local_reference(): void
    {
        Storage::disk('public')->put('homepage/shared.png', 'shared image');
        $slide = HomepageSlide::create(['image_path' => 'https://catalog.test:443/storage/homepage/shared.png?v=2']);

        app(ManagedImage::class)->deleteUnreferenced('/storage/homepage/shared.png');
        Storage::disk('public')->assertExists('homepage/shared.png');

        $slide->delete();
        app(ManagedImage::class)->deleteUnreferenced('/storage/homepage/shared.png');
        Storage::disk('public')->assertMissing('homepage/shared.png');
    }

    public function test_product_references_keep_a_managed_image_until_the_last_reference_is_removed(): void
    {
        Storage::disk('public')->put('homepage/shared.webp', 'shared image');
        $product = Product::factory()->create(['image_url' => '/storage/homepage/shared.webp?version=1']);

        app(ManagedImage::class)->deleteUnreferenced('https://catalog.test/storage/homepage/shared.webp');
        Storage::disk('public')->assertExists('homepage/shared.webp');

        $product->delete();
        app(ManagedImage::class)->deleteUnreferenced('/storage/homepage/shared.webp');
        Storage::disk('public')->assertMissing('homepage/shared.webp');
    }

    public static function unmanagedReferences(): array
    {
        return [
            'external host' => ['https://cdn.example.com/storage/homepage/keep.png'],
            'other scheme' => ['http://catalog.test/storage/homepage/keep.png'],
            'other port' => ['https://catalog.test:444/storage/homepage/keep.png'],
            'protocol relative' => ['//catalog.test/storage/homepage/keep.png'],
            'parent traversal' => ['/storage/homepage/../keep.png'],
            'encoded traversal' => ['/storage/homepage/%2e%2e%2fkeep.png'],
            'unmanaged folder' => ['/storage/uploads/keep.png'],
            'backslash' => ['/storage/homepage/\\keep.png'],
        ];
    }

    #[DataProvider('unmanagedReferences')]
    public function test_unmanaged_references_cannot_delete_files(string $reference): void
    {
        Storage::disk('public')->put('homepage/keep.png', 'keep');

        $this->assertNull(ImageUrl::managedPath($reference));
        app(ManagedImage::class)->deleteUnreferenced($reference);

        Storage::disk('public')->assertExists('homepage/keep.png');
    }
}
