<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The backend UI is built from the `admin-*` classes in resources/css/app.css.
 * These tests pin that contract down: every control comes from the shared
 * classes, so heights, colours and dark mode stay consistent, and nobody has to
 * re-derive a form layout with one-off Tailwind utilities or inline <style>.
 */
class AdminUiConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function adminPageUrls(): array
    {
        return [
            route('dashboard'),
            route('products.index'),
            route('products.create'),
            route('products.import'),
            route('homepage.index'),
            route('homepage-slides.index'),
            route('users.index'),
            route('settings.edit'),
            route('profile.edit'),
        ];
    }

    public function test_admin_pages_avoid_hardcoded_tailwind_palette_classes(): void
    {
        $owner = User::factory()->owner()->create();

        foreach ($this->adminPageUrls() as $url) {
            $response = $this->actingAs($owner)->get($url);
            $response->assertOk();

            // Fixed greys/reds need a `[data-bs-theme='dark']` patch for every
            // new class; the themed `admin-*` classes do not.
            foreach (['text-gray-', 'bg-gray-', 'border-gray-', 'text-red-', 'indigo-'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    (string) $response->getContent(),
                    "{$url} still uses the hardcoded Tailwind class `{$needle}`."
                );
            }
        }
    }

    public function test_admin_pages_have_no_inline_style_blocks(): void
    {
        $owner = User::factory()->owner()->create();

        foreach ($this->adminPageUrls() as $url) {
            $response = $this->actingAs($owner)->get($url);

            $response->assertOk();
            // Layout lives in app.css so the nav variables cannot be overridden
            // page by page (see docs/DEVELOPMENT.md, 布局与导航规则).
            $response->assertDontSee('<style', false);
        }
    }

    public function test_product_form_caps_field_widths_and_moves_wording_into_hints(): void
    {
        $editor = User::factory()->editor()->create();

        $response = $this->actingAs($editor)->get(route('products.create'));

        $response->assertOk();
        // Capped shell + capped grid tracks: no 1700px-wide text boxes.
        $response->assertSee('admin-form-shell', false);
        $response->assertSee('admin-form-grid', false);
        // Short values (状态 / 价格 / 电池容量) keep short boxes.
        $response->assertSee('admin-field-narrow', false);
        $response->assertSee('admin-hint', false);
        $response->assertSee('admin-form-actions', false);
    }

    public function test_list_pages_share_one_filter_bar(): void
    {
        $owner = User::factory()->owner()->create();

        foreach ([route('products.index'), route('users.index')] as $url) {
            $response = $this->actingAs($owner)->get($url);

            $response->assertOk();
            $response->assertSee('admin-filter-bar', false);
            // The keyword box is capped instead of stretching the whole panel.
            $response->assertSee('admin-field-keyword', false);
            $response->assertSee('admin-filter-actions', false);
        }
    }

    public function test_file_pickers_use_the_shared_file_input_class(): void
    {
        $editor = User::factory()->editor()->create();

        foreach ([route('products.import'), route('homepage-slides.index')] as $url) {
            $response = $this->actingAs($editor)->get($url);

            $response->assertOk();
            $response->assertSee('admin-file-input', false);
            // The import page used to hand-roll `file:` utilities on a text input.
            $response->assertDontSee('file:bg-', false);
        }
    }

    public function test_form_pages_centre_their_measure(): void
    {
        $owner = User::factory()->owner()->create();

        // The page header carries the same shell class as the panel under it, so
        // the title keeps the panel's left edge instead of the 1760px
        // container's — which is what made these pages look left-hung.
        foreach ([route('settings.edit'), route('profile.edit'), route('products.import')] as $url) {
            $response = $this->actingAs($owner)->get($url);

            $response->assertOk();
            $this->assertSame(
                2,
                substr_count((string) $response->getContent(), 'admin-form-shell-narrow'),
                "{$url} should wear the narrow shell on both the header and the content."
            );
        }

        $this->assertSame(
            2,
            substr_count((string) $this->actingAs($owner)->get(route('products.create'))->getContent(), 'admin-form-shell')
        );

        // Centring is a single rule in app.css: without it the capped measure
        // sits at the left edge of the container.
        $this->assertMatchesRegularExpression(
            '/\.admin-form-shell,\s*\.admin-form-shell-narrow\s*\{[^}]*margin-inline:\s*auto/',
            (string) file_get_contents(resource_path('css/app.css'))
        );
    }

    public function test_guest_auth_pages_use_the_same_controls_as_the_backend(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $url) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee('admin-input', false);
            $response->assertSee('admin-label', false);
            $response->assertSee('admin-button-primary', false);
        }
    }

    public function test_guest_pages_offer_a_way_back_to_the_public_site(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $url) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee('admin-guest-footer', false);
            $response->assertSee('返回首页', false);
        }
    }
}
