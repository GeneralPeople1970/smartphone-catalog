<?php

namespace Tests\Feature;

use App\Models\HomepageSlide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageSlideStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_replace_and_delete_a_slide_in_public_storage(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->editor()->create());

        $this->post('/admin/homepage-slides', [
            'title' => 'Storage slide',
            'image' => UploadedFile::fake()->image('first.png'),
            'is_active' => '1',
        ])->assertRedirect(route('homepage-slides.index'));

        $slide = HomepageSlide::query()->where('title', 'Storage slide')->firstOrFail();
        $firstPath = str_replace('/storage/', '', $slide->image_path);

        $this->assertStringStartsWith('/storage/homepage/', $slide->image_path);
        Storage::disk('public')->assertExists($firstPath);

        $this->put("/admin/homepage-slides/{$slide->id}", [
            'title' => 'Storage slide',
            'image' => UploadedFile::fake()->image('replacement.png'),
            'is_active' => '1',
        ])->assertRedirect(route('homepage-slides.index'));

        $slide->refresh();
        $replacementPath = str_replace('/storage/', '', $slide->image_path);

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($replacementPath);

        $this->delete("/admin/homepage-slides/{$slide->id}")
            ->assertRedirect(route('homepage-slides.index'));

        Storage::disk('public')->assertMissing($replacementPath);
        $this->assertDatabaseMissing('homepage_slides', ['id' => $slide->id]);
    }

    public function test_migration_command_copies_verifies_updates_and_deletes_a_legacy_slide(): void
    {
        Storage::fake('public');
        HomepageSlide::query()->delete();

        $filename = 'migration-command-test.png';
        $sourcePath = public_path('dist/img/homepage/'.$filename);
        File::ensureDirectoryExists(dirname($sourcePath));
        File::put($sourcePath, 'verified slide contents');

        try {
            $slide = HomepageSlide::create([
                'title' => 'Legacy slide',
                'image_path' => '/dist/img/homepage/'.$filename,
                'sort_order' => 10,
                'is_active' => true,
            ]);

            $this->artisan('homepage-slides:migrate-storage', ['--delete-source' => true])
                ->expectsOutput('Migrated 1 slide image reference(s).')
                ->expectsOutput('Deleted 1 verified legacy file(s).')
                ->assertSuccessful();

            $this->assertSame('/storage/homepage/'.$filename, $slide->refresh()->image_path);
            Storage::disk('public')->assertExists('homepage/'.$filename);
            $this->assertFileDoesNotExist($sourcePath);
        } finally {
            File::delete($sourcePath);
        }
    }

    public function test_migration_command_does_not_overwrite_a_conflicting_storage_file(): void
    {
        Storage::fake('public');
        HomepageSlide::query()->delete();

        $filename = 'migration-conflict-test.png';
        $legacyPath = '/dist/img/homepage/'.$filename;
        $sourcePath = public_path('dist/img/homepage/'.$filename);
        File::ensureDirectoryExists(dirname($sourcePath));
        File::put($sourcePath, 'legacy contents');
        Storage::disk('public')->put('homepage/'.$filename, 'different contents');

        try {
            $slide = HomepageSlide::create([
                'title' => 'Conflicting slide',
                'image_path' => $legacyPath,
                'sort_order' => 10,
                'is_active' => true,
            ]);

            $this->artisan('homepage-slides:migrate-storage')
                ->expectsOutput("Checksum verification failed for slide {$slide->id}: {$legacyPath}")
                ->assertFailed();

            $this->assertSame($legacyPath, $slide->refresh()->image_path);
            $this->assertFileExists($sourcePath);
        } finally {
            File::delete($sourcePath);
        }
    }
}
