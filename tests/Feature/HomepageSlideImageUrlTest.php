<?php

namespace Tests\Feature;

use App\Models\HomepageSlide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomepageSlideImageUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::factory()->editor()->create());
    }

    public static function imageUrls(): array
    {
        return [
            'https' => ['https://cdn.example.com/banner.jpg'],
            'http' => ['http://cdn.example.com/banner.jpg'],
            'site path' => ['/assets/banner.jpg'],
        ];
    }

    #[DataProvider('imageUrls')]
    public function test_a_slide_can_reference_an_image_without_uploading_a_file(string $url): void
    {
        config(['app.url' => 'http://localhost']);

        $this->post(route('homepage-slides.store'), [
            'title' => 'Referenced slide',
            'image_url' => $url,
            'link_url' => '/phones/123',
            'is_active' => '1',
        ])->assertRedirect(route('homepage-slides.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('homepage_slides', [
            'title' => 'Referenced slide',
            'image_path' => $url,
            'link_url' => '/phones/123',
            'is_active' => true,
        ]);
        $this->assertEmpty(Storage::disk('public')->allFiles());

        $this->getJson('/api/homepage-slides?fields=image,linkUrl')
            ->assertOk()
            ->assertExactJson([['image' => $url, 'linkUrl' => '/phones/123']]);

        $this->get(route('homepage-slides.index'))
            ->assertOk()
            ->assertSee('src="'.$url.'"', false);
    }

    public function test_creating_a_slide_requires_a_file_or_image_url(): void
    {
        $this->post(route('homepage-slides.store'), ['title' => 'No image'])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('homepage_slides', 0);
    }

    public static function unsafeImageUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:image/png;base64,AAAA'],
            'protocol relative' => ['//evil.example/banner.png'],
            'backslash host' => ['/\\evil.example/banner.png'],
            'file' => ['file:///etc/passwd'],
            'missing host' => ['https:///banner.png'],
            'whitespace' => ['https://cdn.example.com/a b.png'],
        ];
    }

    #[DataProvider('unsafeImageUrls')]
    public function test_unsafe_image_urls_are_rejected_on_create_and_update(string $url): void
    {
        $slide = $this->createReferencedSlide('/assets/original.png');

        $this->post(route('homepage-slides.store'), [
            'image_url' => $url,
        ])->assertSessionHasErrors('image_url');

        $this->put(route('homepage-slides.update', $slide), [
            'image_url' => $url,
        ])->assertSessionHasErrors('image_url');

        $this->assertSame('/assets/original.png', $slide->refresh()->image_path);
        $this->assertDatabaseCount('homepage_slides', 1);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_image_urls_are_limited_to_the_storage_column_length(): void
    {
        $this->post(route('homepage-slides.store'), [
            'image_url' => 'https://cdn.example.com/'.str_repeat('a', 2048),
        ])->assertSessionHasErrors('image_url');

        $this->assertDatabaseCount('homepage_slides', 0);
    }

    public function test_a_referenced_image_can_be_changed_or_preserved_when_editing(): void
    {
        $slide = $this->createReferencedSlide('https://cdn.example.com/original.png');
        $replacement = 'https://cdn.example.com/replacement.png';

        $this->put(route('homepage-slides.update', $slide), [
            'title' => 'Updated slide',
            'image_url' => $replacement,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame($replacement, $slide->refresh()->image_path);

        foreach ([[], ['image_url' => '']] as $input) {
            $this->put(route('homepage-slides.update', $slide), [
                'title' => 'Metadata only',
                'is_active' => '1',
                ...$input,
            ])->assertSessionHasNoErrors();

            $this->assertSame($replacement, $slide->refresh()->image_path);
        }

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_an_upload_can_be_replaced_with_a_reference_and_back_again(): void
    {
        $this->post(route('homepage-slides.store'), [
            'image' => UploadedFile::fake()->image('original.png'),
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $slide = HomepageSlide::query()->firstOrFail();
        $originalPath = substr($slide->image_path, strlen('/storage/'));
        $url = 'https://cdn.example.com/replacement.png';

        $this->put(route('homepage-slides.update', $slide), [
            'image_url' => $url,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame($url, $slide->refresh()->image_path);
        Storage::disk('public')->assertMissing($originalPath);

        $this->put(route('homepage-slides.update', $slide), [
            'image_url' => $url,
            'image' => UploadedFile::fake()->image('replacement.png'),
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertStringStartsWith('/storage/homepage/', $slide->refresh()->image_path);
        Storage::disk('public')->assertExists(substr($slide->image_path, strlen('/storage/')));
    }

    public function test_saving_the_current_upload_path_does_not_delete_the_image(): void
    {
        Storage::disk('public')->put('homepage/original.png', 'original image');
        $slide = $this->createReferencedSlide('/storage/homepage/original.png');

        $this->put(route('homepage-slides.update', $slide), [
            'image_url' => $slide->image_path,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertExists('homepage/original.png');
    }

    public function test_a_shared_upload_is_only_deleted_after_its_last_reference_is_removed(): void
    {
        Storage::disk('public')->put('homepage/shared.png', 'shared image');
        $first = $this->createReferencedSlide('/storage/homepage/shared.png');
        $second = $this->createReferencedSlide('/storage/homepage/shared.png');

        $this->delete(route('homepage-slides.destroy', $first))
            ->assertRedirect(route('homepage-slides.index'));

        Storage::disk('public')->assertExists('homepage/shared.png');

        $this->put(route('homepage-slides.update', $second), [
            'image_url' => 'https://cdn.example.com/replacement.png',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing('homepage/shared.png');
    }

    public function test_deleting_references_does_not_delete_files_outside_the_managed_directory(): void
    {
        Storage::disk('public')->put('outside.png', 'unrelated image');
        Storage::disk('public')->put('homepage/keep.png', 'another image');

        foreach ([
            'https://cdn.example.com/banner.png',
            '/storage/outside.png',
            '/storage/homepage/../outside.png',
        ] as $url) {
            $slide = $this->createReferencedSlide($url);

            $this->delete(route('homepage-slides.destroy', $slide))
                ->assertRedirect(route('homepage-slides.index'));

            Storage::disk('public')->assertExists('outside.png');
            Storage::disk('public')->assertExists('homepage/keep.png');
        }
    }

    public function test_the_admin_preview_uses_the_same_url_safety_as_the_public_api(): void
    {
        $this->createReferencedSlide('//evil.example/banner.png');

        $this->get(route('homepage-slides.index'))
            ->assertOk()
            ->assertSee('src="'.asset('assets/logo.png').'"', false)
            ->assertDontSee('src="//evil.example/banner.png"', false);
    }

    public function test_failed_edits_only_restore_input_to_the_submitted_slide(): void
    {
        $first = $this->createReferencedSlide('/assets/first.png');
        $second = $this->createReferencedSlide('/assets/second.png');

        $this->from(route('homepage-slides.index'))
            ->put(route('homepage-slides.update', $first), [
                '_slide_form' => (string) $first->id,
                'title' => 'Unsaved title',
                'image_url' => 'javascript:alert(1)',
            ])->assertSessionHasErrors('image_url');

        $response = $this->get(route('homepage-slides.index'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());

        $this->assertSame('javascript:alert(1)', $document->getElementById("image-url-{$first->id}")->getAttribute('value'));
        $this->assertSame('/assets/second.png', $document->getElementById("image-url-{$second->id}")->getAttribute('value'));
        $this->assertSame('', $document->getElementById('image_url')->getAttribute('value'));
        $this->assertSame('/assets/first.png', $first->refresh()->image_path);
    }

    private function createReferencedSlide(string $url): HomepageSlide
    {
        return HomepageSlide::create([
            'title' => 'Referenced slide',
            'image_path' => $url,
            'is_active' => true,
        ]);
    }
}
