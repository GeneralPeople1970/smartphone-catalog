<?php

use App\Models\HomepageFeaturedPhone;
use App\Models\HomepageSlide;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';

$runtime = getenv('BROWSER_TEST_RUNTIME');
$database = getenv('DB_DATABASE');
if (getenv('APP_ENV') !== 'browser-testing' || ! $runtime || $database !== $runtime.DIRECTORY_SEPARATOR.'browser.sqlite') {
    throw new RuntimeException('Browser fixtures require their own disposable database.');
}
if (file_exists($database)) {
    throw new RuntimeException('Use a new BROWSER_TEST_RUNTIME for each browser test run.');
}
touch($database);
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Artisan::call('migrate', ['--force' => true]);

User::factory()->owner()->create([
    'name' => 'Browser owner',
    'email' => 'browser-owner@example.test',
    'password' => 'browser-test-password',
]);

foreach (['Xiaomi' => 53, 'Apple' => 28] as $brand => $count) {
    for ($index = 1; $index <= $count; $index++) {
        $name = sprintf('QA %s %02d', $brand, $index);
        Product::factory()->create([
            'brand' => $brand,
            'name' => $name,
            'slug' => str_replace(' ', '-', strtolower($name)),
            'image_url' => '/assets/logo.png',
            'price' => $index === 1 ? '3999 起' : '4299',
            'soc_name' => 'Snapdragon 8',
            'battery_capacity' => 5000,
            'specs' => ['saledate' => 20260901, 'feature' => '浏览器回归测试机型'],
        ]);
    }
}
foreach ([1, 2] as $index) {
    HomepageFeaturedPhone::create([
        'product_id' => Product::query()->where('brand', 'Xiaomi')->orderBy('id')->skip($index - 1)->firstOrFail()->id,
        'title' => 'QA 推荐'.$index,
        'description' => '推荐描述'.$index,
        'sort_order' => $index * 10,
        'is_active' => true,
    ]);
    HomepageSlide::create([
        'title' => 'QA 轮播'.$index,
        'image_path' => '/assets/logo.png',
        'sort_order' => $index * 10,
        'is_active' => true,
    ]);
}
echo "Browser fixtures prepared in an isolated SQLite database.\n";
