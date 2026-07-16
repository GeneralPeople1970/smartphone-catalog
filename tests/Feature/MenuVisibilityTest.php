<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu visibility is UX only — these tests also re-assert that the server
 * keeps enforcing authorization regardless of what the menu shows.
 */
class MenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_MENU_LABELS = ['控制台', '手机管理', '热门管理', '轮播图管理'];

    // --- plain user --------------------------------------------------------

    public function test_plain_user_menu_shows_only_home_profile_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');
        $response->assertOk();

        foreach (self::ADMIN_MENU_LABELS as $label) {
            $response->assertDontSee($label);
        }
        $response->assertDontSee('用户管理');

        $response->assertSee('首页');
        $response->assertSee('个人资料');
        $response->assertSee('退出登录');
    }

    public function test_plain_user_still_cannot_open_backend_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/admin/products')->assertForbidden();
        $this->actingAs($user)->get('/admin/homepage')->assertForbidden();
        $this->actingAs($user)->get('/admin/homepage-slides')->assertForbidden();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_api_me_reports_no_admin_capability_for_plain_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('user.canAccessAdmin', false);
    }

    // --- editor ------------------------------------------------------------

    public function test_editor_menu_shows_management_but_not_user_management(): void
    {
        $editor = User::factory()->editor()->create();

        $response = $this->actingAs($editor)->get('/dashboard');
        $response->assertOk();

        foreach (self::ADMIN_MENU_LABELS as $label) {
            $response->assertSee($label);
        }
        $response->assertSee('个人资料');
        $response->assertDontSee('用户管理');
    }

    public function test_editor_cannot_open_user_management(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
    }

    public function test_api_me_reports_admin_capability_for_editor(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.canAccessAdmin', true);
    }

    // --- admin / owner -----------------------------------------------------

    public function test_admin_menu_includes_user_management(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertOk();

        foreach ([...self::ADMIN_MENU_LABELS, '用户管理', '个人资料'] as $label) {
            $response->assertSee($label);
        }

        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_owner_menu_includes_user_management(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->get('/dashboard');
        $response->assertOk();

        foreach ([...self::ADMIN_MENU_LABELS, '用户管理', '个人资料'] as $label) {
            $response->assertSee($label);
        }

        $this->actingAs($owner)->get('/admin/users')->assertOk();
    }

    // --- frontend initial auth payload --------------------------------------

    public function test_spa_bootstrap_auth_payload_contains_capability_flag(): void
    {
        if (! file_exists(public_path('frontend/index.html'))) {
            $this->markTestSkipped('Frontend build output not present.');
        }

        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get('/')
            ->assertOk()
            ->assertSee('__SMARTPHONE_CATALOG_AUTH__', false)
            ->assertSee('canAccessAdmin', false);
    }
}
