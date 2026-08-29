<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin-facing switch behind App\Support\SiteSettings. Guards the access
 * rules and the one thing the switch must never do: relabel accounts. Verified
 * stays verified, unverified stays unverified — turning the requirement on only
 * costs the unverified their session.
 */
class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');
    }

    public function test_users_below_admin_cannot_reach_the_settings_page(): void
    {
        foreach ([User::factory()->create(), User::factory()->editor()->create()] as $user) {
            $this->actingAs($user)->get('/admin/settings')->assertForbidden();
            $this->actingAs($user)->put('/admin/settings', [
                'registration_email_verification' => '1',
            ])->assertForbidden();
        }

        $this->assertFalse(SiteSettings::emailVerificationRequired());
    }

    public function test_admins_see_the_current_state_and_the_mail_configuration(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get('/admin/settings');

        $response->assertOk();
        $response->assertSee('注册时验证邮箱');
        // The four-bullet "开启后的行为" block is gone; one line says it instead.
        $response->assertDontSee('开启后的行为');
        $response->assertSee('未验证的账号会被退出登录');
        // MAIL_MAILER is `array` under phpunit.xml — a mailer that delivers nothing.
        $response->assertSee('MAIL_MAILER');
        $response->assertSee('验证码邮件不会真正投递', false);
    }

    public function test_an_unverified_admin_is_warned_before_locking_themselves_out(): void
    {
        $this->actingAs(User::factory()->admin()->unverified()->create())
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('你的邮箱尚未验证');

        // An owner counts as verified, so the warning has nothing to say to them.
        $this->actingAs(User::factory()->owner()->unverified()->create())
            ->get('/admin/settings')
            ->assertOk()
            ->assertDontSee('你的邮箱尚未验证');
    }

    public function test_an_admin_can_turn_verification_on(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/settings', ['registration_email_verification' => '1'])
            ->assertRedirect(route('settings.edit'));

        $this->assertTrue(SiteSettings::emailVerificationRequired());
        $this->assertDatabaseHas('site_settings', [
            'key' => 'registration_email_verification',
            'value' => '1',
        ]);
    }

    public function test_an_admin_can_turn_verification_off_again(): void
    {
        SiteSettings::putBool(SiteSettings::REGISTRATION_EMAIL_VERIFICATION, true);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/settings', ['registration_email_verification' => '0'])
            ->assertRedirect(route('settings.edit'));

        $this->assertFalse(SiteSettings::emailVerificationRequired());
    }

    public function test_enabling_verification_does_not_relabel_existing_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $legacy = User::factory()->unverified()->create();
        $legacyEditor = User::factory()->editor()->unverified()->create();

        $this->actingAs($admin)->put('/admin/settings', [
            'registration_email_verification' => '1',
        ])->assertRedirect(route('settings.edit'));

        // Verified is verified and unverified is unverified: the switch decides
        // whether the requirement is enforced, never who has met it.
        $this->assertNull($legacy->fresh()->email_verified_at);
        $this->assertNull($legacyEditor->fresh()->email_verified_at);

        // What they do lose is their session, on the next request.
        $this->actingAs($legacyEditor->fresh())->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_enabling_verification_reports_how_many_accounts_are_affected(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->unverified()->create();
        User::factory()->editor()->unverified()->create();
        // Owners are never pending, so they are not counted.
        User::factory()->owner()->unverified()->create();

        $this->actingAs($admin)
            ->put('/admin/settings', ['registration_email_verification' => '1'])
            ->assertSessionHas('status', fn (string $status) => str_contains($status, '2 个未验证账号'));
    }

    public function test_an_omitted_checkbox_turns_the_switch_off(): void
    {
        SiteSettings::putBool(SiteSettings::REGISTRATION_EMAIL_VERIFICATION, true);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings', [])->assertRedirect(route('settings.edit'));

        $this->assertFalse(SiteSettings::emailVerificationRequired());
    }

    public function test_the_settings_link_is_only_shown_to_admins_and_owners(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee(route('settings.edit'));

        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(route('settings.edit'));
    }
}
