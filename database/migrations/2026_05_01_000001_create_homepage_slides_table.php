<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_slides', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('image_path', 2048);
            $table->string('link_url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $directory = public_path('dist/img/homepage');

        if (! is_dir($directory)) {
            return;
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $slides = collect(File::files($directory))
            ->filter(fn (SplFileInfo $file) => in_array(strtolower($file->getExtension()), $allowedExtensions, true))
            ->sortBy(fn (SplFileInfo $file) => $file->getFilename())
            ->values()
            ->map(fn (SplFileInfo $file, int $index) => [
                'title' => pathinfo($file->getFilename(), PATHINFO_FILENAME),
                'image_path' => '/dist/img/homepage/'.$file->getFilename(),
                'link_url' => null,
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($slides !== []) {
            DB::table('homepage_slides')->insert($slides);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_slides');
    }
};
