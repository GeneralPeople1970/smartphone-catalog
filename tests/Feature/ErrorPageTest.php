<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A deployment behind a stock web-server vhost hits a 404 long before it hits
 * anything else, so both 404 pages have to exist and stand on their own:
 * resources/views/errors/404.blade.php for requests that reach PHP, and the
 * static public/404.html for the ones that never do.
 */
class ErrorPageTest extends TestCase
{
    public function test_unknown_application_paths_render_the_custom_404_page(): void
    {
        foreach (['/admin/not-a-route', '/api/not-a-route', '/assets/not-a-file.png'] as $path) {
            $response = $this->get($path);

            $response->assertNotFound();
            $response->assertSee('页面未找到');
            $response->assertSee('返回首页');
        }
    }

    public function test_the_404_page_does_not_depend_on_the_vite_build(): void
    {
        $blade = (string) file_get_contents(resource_path('views/errors/404.blade.php'));

        // An error page that needs the build manifest turns a 404 into a 500 the
        // moment assets are missing, which is exactly when it is needed most.
        $this->assertStringNotContainsString('@vite', $blade);

        $response = $this->get('/admin/not-a-route');
        $response->assertNotFound();
        $response->assertDontSee('/build/', false);
        $response->assertSee('assets/logo.png', false);
    }

    public function test_the_static_404_page_mirrors_the_rendered_one(): void
    {
        $static = (string) file_get_contents(public_path('404.html'));

        foreach (['404', '页面未找到', '返回首页', '/assets/logo.png', 'error-panel'] as $needle) {
            $this->assertStringContainsString($needle, $static);
        }

        // It is served as a plain file: nothing here may need Blade to render.
        $this->assertStringNotContainsString('{{', $static);
        $this->assertStringNotContainsString('@vite', $static);
    }
}
