<?php

namespace Tests\Feature;

use App\Models\HomepageSlide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageSlideUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::factory()->editor()->create());
    }

    public function test_a_php_named_file_is_rejected_and_never_written(): void
    {
        // A real PNG wearing a .php name must not be stored at all.
        $this->from(route('homepage-slides.index'))
            ->post('/admin/homepage-slides', [
                'title' => 'Polyglot',
                'image' => $this->realImageUpload('payload.php', 'png'),
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('homepage_slides', ['title' => 'Polyglot']);
        $this->assertEmpty(Storage::disk('public')->files('homepage'));
    }

    public function test_extension_is_derived_from_content_not_the_client_name(): void
    {
        // PNG bytes wearing a .jpg client name are stored with the detected .png
        // extension — the client file name never decides the stored extension.
        $this->post('/admin/homepage-slides', [
            'title' => 'Mismatch',
            'image' => $this->realImageUpload('actually-a-png.jpg', 'png'),
            'is_active' => '1',
        ])->assertRedirect(route('homepage-slides.index'));

        $slide = HomepageSlide::query()->where('title', 'Mismatch')->firstOrFail();

        $this->assertMatchesRegularExpression('#^/storage/homepage/[A-Za-z0-9]{40}\.png$#', $slide->image_path);
        $this->assertStringNotContainsString('actually-a-png', $slide->image_path);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $slide->image_path));
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        $this->from(route('homepage-slides.index'))
            ->post('/admin/homepage-slides', [
                'title' => 'Evil',
                'image' => UploadedFile::fake()->createWithContent('evil.php', '<?php echo "pwned"; ?>'),
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('homepage_slides', ['title' => 'Evil']);
    }

    public function test_an_oversized_image_is_rejected(): void
    {
        $this->from(route('homepage-slides.index'))
            ->post('/admin/homepage-slides', [
                'title' => 'Too big',
                'image' => UploadedFile::fake()->image('huge.png', 4001, 10),
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('homepage_slides', ['title' => 'Too big']);
    }

    public function test_stored_names_are_random_and_never_reuse_the_original_name(): void
    {
        foreach (['brand-logo.png', 'brand-logo.png'] as $index => $name) {
            $this->post('/admin/homepage-slides', [
                'title' => "Slide {$index}",
                'image' => UploadedFile::fake()->image($name, 40, 40),
                'is_active' => '1',
            ])->assertRedirect(route('homepage-slides.index'));
        }

        $paths = HomepageSlide::query()->pluck('image_path');

        $this->assertCount(2, $paths->unique(), 'Identical uploads must get distinct random names.');
        foreach ($paths as $path) {
            $this->assertMatchesRegularExpression('#^/storage/homepage/[A-Za-z0-9]{40}\.png$#', $path);
            $this->assertStringNotContainsString('brand-logo', $path);
        }
    }

    /**
     * Build a real UploadedFile with genuine image bytes under an arbitrary
     * client name, so the server-side MIME detection (finfo) sees the content
     * rather than the fake factory's extension-based guess.
     */
    private function realImageUpload(string $name, string $format): UploadedFile
    {
        $gd = imagecreatetruecolor(32, 32);

        ob_start();
        match ($format) {
            'jpg' => imagejpeg($gd),
            'png' => imagepng($gd),
            'gif' => imagegif($gd),
            'webp' => imagewebp($gd),
        };
        $bytes = (string) ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'slide');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }
}
