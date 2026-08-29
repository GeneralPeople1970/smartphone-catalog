<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin-facing switch behind App\Support\SiteSettings. Guards the access
 * rules and the one non-obvious side effect: enabling verification grandfathers
 * existing accounts so nobody is locked out retroactively.
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
        // MAIL_MAILER is `array` under phpunit.xml — a mailer that delivers nothing.
        $response->assertSee('MAIL_MAILER');
        $response->assertSee('验证邮件不会真正投递', false);
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

    public function test_enabling_verification_grandfathers_existing_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $legacy = User::factory()->unverified()->create();
        $legacyEditor = User::factory()->editor()->unverified()->create();

        $this->actingAs($admin)->put('/admin/settings', [
            'registration_email_verification' => '1',
        ])->assertRedirect(route('settings.edit'));

        // Otherwise the switch would lock out every account created before it,
        // the admin who flipped it included.
        $this->assertTrue($legacy->fresh()->hasVerifiedEmail());
        $this->assertTrue($legacyEditor->fresh()->hasVerifiedEmail());
        $this->actingAs($legacyEditor->fresh())->get('/dashboard')->assertOk();
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
