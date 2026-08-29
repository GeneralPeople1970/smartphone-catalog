<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Registration email verification is an operator switch (site setting
 * `registration_email_verification`), so both positions have to behave: off is
 * the historical open registration, on parks new accounts until they confirm.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function requireVerification(bool $required = true): void
    {
        SiteSettings::putBool(SiteSettings::REGISTRATION_EMAIL_VERIFICATION, $required);
    }

    public function test_verification_routes_are_registered(): void
    {
        foreach (['verification.notice', 'verification.verify', 'verification.send'] as $name) {
            $this->assertTrue(Route::has($name), "Route [{$name}] is missing.");
        }
    }

    public function test_verification_is_off_until_an_operator_turns_it_on(): void
    {
        $this->assertFalse(SiteSettings::emailVerificationRequired());
    }

    public function test_registration_auto_verifies_and_sends_nothing_while_off(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Open Registration',
            'email' => 'open@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertTrue(User::where('email', 'open@example.com')->firstOrFail()->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_unverified_accounts_keep_full_access_while_off(): void
    {
        $editor = User::factory()->editor()->unverified()->create();

        $this->actingAs($editor)->get('/dashboard')->assertOk();
        $this->actingAs($editor)->get('/admin/products')->assertOk();
        $this->actingAs($editor)->get('/profile')->assertOk();
    }

    public function test_registration_parks_the_account_and_mails_a_link_while_on(): void
    {
        Notification::fake();
        $this->requireVerification();

        $response = $this->post('/register', [
            'name' => 'Pending User',
            'email' => 'pending@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'pending@example.com')->firstOrFail();

        $this->assertAuthenticated();
        $this->assertFalse($user->hasVerifiedEmail());
        $response->assertRedirect(route('verification.notice', absolute: false));
        $response->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_register_page_warns_that_the_address_must_be_reachable(): void
    {
        $this->requireVerification();

        $this->get('/register')->assertOk()->assertSee('需要验证邮箱');
    }

    public function test_an_unverified_account_is_held_at_the_notice_page_while_on(): void
    {
        $this->requireVerification();
        $owner = User::factory()->owner()->unverified()->create();

        foreach (['/dashboard', '/admin/products', '/admin/users', '/admin/settings'] as $url) {
            $this->actingAs($owner)->get($url)->assertRedirect(route('verification.notice'));
        }

        // Fixing a mistyped address is the way out, so /profile stays reachable.
        $this->actingAs($owner)->get('/profile')->assertOk();
        $this->actingAs($owner)->get('/verify-email')->assertOk()->assertSee('重新发送验证邮件');
    }

    public function test_a_signed_link_verifies_the_address(): void
    {
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }

    public function test_a_signed_link_with_the_wrong_hash_does_not_verify(): void
    {
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('someone-else@example.com'),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_notice_page_can_resend_the_link(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->from('/verify-email')
            ->post('/email/verification-notification')
            ->assertRedirect('/verify-email')
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_an_already_verified_user_is_sent_on_instead_of_mailed_again(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/verify-email')
            ->assertRedirect(route('dashboard', absolute: false));
        $this->actingAs($user)->post('/email/verification-notification')
            ->assertRedirect(route('dashboard', absolute: false));

        Notification::assertNothingSent();
    }

    public function test_turning_the_switch_back_off_releases_a_waiting_account(): void
    {
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));

        $this->requireVerification(false);

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/verify-email')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_password_reset_flow_is_untouched(): void
    {
        $this->get('/forgot-password')->assertOk();

        foreach (['password.request', 'password.email', 'password.reset', 'password.store'] as $name) {
            $this->assertTrue(Route::has($name), "Route [{$name}] is missing.");
        }
    }
}
