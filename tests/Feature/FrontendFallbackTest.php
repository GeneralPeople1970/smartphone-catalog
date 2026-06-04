<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendFallbackTest extends TestCase
{
    public function test_public_frontend_routes_are_served_by_the_spa_fallback(): void
    {
        $this->get('/category')->assertOk()->assertSee('<div id="app">', false);
        $this->get('/phone/123')->assertOk()->assertSee('<div id="app">', false);
    }

    public function test_reserved_application_and_asset_prefixes_are_not_served_by_the_spa_fallback(): void
    {
        foreach ([
            '/admin/not-a-route',
            '/api/not-a-route',
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
