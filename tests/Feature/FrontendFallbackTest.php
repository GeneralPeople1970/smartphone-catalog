<?php

namespace Tests\Feature;

use App\Support\PhoneCatalog;
use Tests\TestCase;

class FrontendFallbackTest extends TestCase
{
    public function test_public_frontend_routes_are_served_by_the_spa_fallback(): void
    {
        $this->get('/category')->assertOk()->assertSee('<div id="app">', false);
        $this->get('/phone/123')->assertOk()->assertSee('<div id="app">', false);
    }

    public function test_brand_routes_from_the_api_contract_are_served_by_the_spa_fallback(): void
    {
        $paths = collect(PhoneCatalog::brands())
            ->pluck('path')
            ->push('/LIANXIANG')
            ->push('/LENOVO_XIAOXIN')
            ->unique()
            ->values();

        foreach ($paths as $path) {
            $this->get($path)->assertOk()->assertSee('<div id="app">', false);
        }
    }

    public function test_phone_placeholder_asset_exists(): void
    {
        $this->assertFileExists(public_path('assets/phone-placeholder.svg'));
    }

    public function test_reserved_application_and_asset_prefixes_are_not_served_by_the_spa_fallback(): void
    {
        foreach ([
            '/admin/not-a-route',
            '/admin/theme',
            '/api/not-a-route',
            '/api/site-theme',
            '/assets/not-a-file.png',
            '/build/not-a-file.js',
            '/dist/not-a-file.js',
            '/frontend/not-a-file.js',
            '/storage/not-a-file.png',
        ] as $path) {
            $response = $this->get($path);

            $this->assertNotSame(200, $response->getStatusCode(), "{$path} was served by the SPA fallback.");
            $response->assertDontSee('<div id="app">', false);
        }
    }

    public function test_health_route_remains_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
