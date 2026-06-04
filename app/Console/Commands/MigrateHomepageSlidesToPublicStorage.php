<?php

namespace App\Console\Commands;

use App\Models\HomepageSlide;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class MigrateHomepageSlidesToPublicStorage extends Command
{
    protected $signature = 'homepage-slides:migrate-storage {--delete-source : Delete verified legacy files after all database references are updated}';

    protected $description = 'Copy legacy homepage slide images from public/dist into public storage';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $migrated = 0;
        $deleted = 0;
        $failed = 0;

        HomepageSlide::query()
            ->where('image_path', 'like', '/dist/img/homepage/%')
            ->orderBy('id')
            ->each(function (HomepageSlide $slide) use ($disk, &$migrated, &$deleted, &$failed): void {
                $legacyPath = $slide->image_path;
                $filename = basename($legacyPath);
                $sourcePath = public_path('dist/img/homepage/'.$filename);
                $targetPath = 'homepage/'.$filename;

                if (! is_file($sourcePath)) {
                    $this->error("Source file is missing for slide {$slide->id}: {$legacyPath}");
                    $failed++;

                    return;
                }

                if (! $disk->exists($targetPath) && ! $this->copyToDisk($sourcePath, $targetPath)) {
                    $this->error("Unable to copy slide {$slide->id} to public storage.");
                    $failed++;

                    return;
                }

                $targetAbsolutePath = $disk->path($targetPath);

                if (! is_file($targetAbsolutePath) || hash_file('sha256', $sourcePath) !== hash_file('sha256', $targetAbsolutePath)) {
                    $this->error("Checksum verification failed for slide {$slide->id}: {$legacyPath}");
                    $failed++;

                    return;
                }

                $slide->update(['image_path' => '/storage/'.$targetPath]);
                $migrated++;

                if (! $this->option('delete-source')) {
                    return;
                }

                $hasReferences = HomepageSlide::query()->where('image_path', $legacyPath)->exists();

                if (! $hasReferences && is_file($sourcePath)) {
                    if (File::delete($sourcePath)) {
                        $deleted++;
                    } else {
                        $this->error("Unable to delete verified legacy file: {$legacyPath}");
                        $failed++;
                    }
                }
            });

        $this->info("Migrated {$migrated} slide image reference(s).");

        if ($this->option('delete-source')) {
            $this->info("Deleted {$deleted} verified legacy file(s).");
        }

        if ($failed > 0) {
            $this->error("Failed to migrate {$failed} slide image reference(s).");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function copyToDisk(string $sourcePath, string $targetPath): bool
    {
        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            return false;
        }

        try {
            return Storage::disk('public')->put($targetPath, $stream);
        } finally {
            fclose($stream);
        }
    }
}
