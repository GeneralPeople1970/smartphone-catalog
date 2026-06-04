<?php

use App\Models\Product;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('phones:import {--path= : Absolute or project-relative private data directory} {--status=published} {--fresh}', function () {
    $status = (string) $this->option('status');

    if (! in_array($status, ['draft', 'published'], true)) {
        $this->error('The --status option must be draft or published.');

        return 1;
    }

    $pathOption = (string) ($this->option('path') ?: config('catalog.phone_data_path'));
    $sourcePath = preg_match('/^[A-Za-z]:[\\\\\\/]/', $pathOption) || str_starts_with($pathOption, '/') || str_starts_with($pathOption, '\\\\')
        ? $pathOption
        : base_path($pathOption);

    if (! is_dir($sourcePath)) {
        $this->error("Phone data directory does not exist: {$sourcePath}");

        return 1;
    }

    $process = new Process(['node', base_path('scripts/parse-phone-data.mjs'), $sourcePath], base_path());
    $process->setTimeout(120);
    $process->run();

    if (! $process->isSuccessful()) {
        $this->error(trim($process->getErrorOutput() ?: $process->getOutput()));

        return 1;
    }

    $records = json_decode(ltrim($process->getOutput(), "\xEF\xBB\xBF"), true);

    if (! is_array($records)) {
        $this->error('The phone data parser did not return valid JSON.');

        return 1;
    }

    if ($this->option('fresh')) {
        Product::whereNotNull('source_key')->delete();
    }

    $created = 0;
    $updated = 0;

    foreach ($records as $record) {
        $product = Product::where('source_key', $record['source_key'])->first()
            ?? Product::where('brand', $record['brand'])->where('name', $record['name'])->first()
            ?? new Product;

        $product->fill(Arr::only($record, [
            'source_key',
            'source_file',
            'source_id',
            'brand',
            'name',
            'slug',
            'image_url',
            'price',
            'soc_name',
            'battery_capacity',
            'specs',
        ]));

        $product->status = $status;

        $product->exists ? $updated++ : $created++;
        $product->save();
    }

    $this->info("Imported {$created} products, updated {$updated} products.");
    $this->info('Total imported phone products: '.Product::whereNotNull('source_key')->count());

    return 0;
})->purpose('Import phone product data from JavaScript or JSON files');
