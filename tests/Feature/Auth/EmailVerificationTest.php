<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Services\EmailVerification;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Registration email verification is an operator switch (site setting
 * `registration_email_verification`), so both positions have to behave: off is
 * the historical open registration, on means an unverified account holds no
 * session at all until it types the code that was mailed to it.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function requireVerification(bool $required = true): void
    {
        SiteSettings::putBool(SiteSettings::REGISTRATION_EMAIL_VERIFICATION, $required);
    }

    /**
     * Mail a code the way the app does and read it back out of the notification.
     */
    private function issueCode(User $user): string
    {
        app(EmailVerification::class)->send($user);

        return $this->mailedCode($user);
    }

    private function mailedCode(User $user): string
    {
        $code = null;

        Notification::assertSentTo($user, VerifyEmailCode::class, function (VerifyEmailCode $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        return (string) $code;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function submitCode(User $user, array $payload)
    {
        return $this->withSession([EmailVerification::SESSION_KEY => $user->getKey()])
            ->from(route('verification.notice'))
            ->post('/verify-email', $payload);
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

    public function test_registration_mails_a_code_and_stays_signed_out_while_on(): void
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

        // Signed out on purpose: an unverified account never holds a session
        // while the requirement is on.
        $this->assertGuest();
        $this->assertFalse($user->hasVerifiedEmail());
        $response->assertRedirect(route('verification.notice', absolute: false));
        $response->assertSessionHas('status', 'verification-code-sent');
        $response->assertSessionHas(EmailVerification::SESSION_KEY, $user->id);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->mailedCode($user));
    }

    public function test_register_page_warns_that_the_address_must_be_reachable(): void
    {
        $this->requireVerification();

        $this->get('/register')->assertOk()->assertSee('需要验证邮箱');
    }

    public function test_the_code_page_names_the_pending_address(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $this->withSession([EmailVerification::SESSION_KEY => $user->getKey()])
            ->get('/verify-email')
            ->assertOk()
            ->assertSee($user->email)
            ->assertSee('重新发送验证码');
    }

    public function test_the_code_page_needs_a_pending_account(): void
    {
        $this->requireVerification();

        $this->get('/verify-email')->assertRedirect(route('login'));
        $this->post('/verify-email', ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_turning_the_switch_on_signs_out_unverified_sessions(): void
    {
        $editor = User::factory()->editor()->unverified()->create();

        $this->actingAs($editor)->get('/dashboard')->assertOk();

        $this->requireVerification();

        foreach (['/dashboard', '/profile', '/admin/products'] as $url) {
            $this->actingAs($editor)->get($url)->assertRedirect(route('login'));
            $this->assertGuest();
        }

        // Kicked out, but not re-labelled: unverified stays unverified and
        // verified stays verified whatever the switch does.
        $this->assertNull($editor->fresh()->email_verified_at);
    }

    public function test_owners_are_exempt_from_verification(): void
    {
        $this->requireVerification();
        $owner = User::factory()->owner()->unverified()->create();

        $this->assertTrue($owner->hasVerifiedEmail());

        foreach (['/dashboard', '/profile', '/admin/users', '/admin/settings'] as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
        }
    }

    public function test_login_turns_an_unverified_account_away_and_mails_a_code(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas(EmailVerification::SESSION_KEY, $user->id);
        $response->assertSessionHas('status', 'verification-code-sent');
        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    public function test_a_verified_account_logs_in_normally_while_on(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        Notification::assertNothingSent();
    }

    public function test_the_mailed_code_verifies_the_address_and_signs_the_user_in(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $code = $this->issueCode($user);

        $this->submitCode($user, ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertNull(session(EmailVerification::SESSION_KEY));
    }

    public function test_a_wrong_code_verifies_nothing(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $code = $this->issueCode($user);

        $this->submitCode($user, ['code' => $this->otherThan($code)])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        // The real code still works — one wrong guess must not burn it.
        $this->submitCode($user, ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_code_is_burned_after_too_many_wrong_guesses(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $code = $this->issueCode($user);
        $wrong = $this->otherThan($code);

        for ($attempt = 0; $attempt < EmailVerification::MAX_ATTEMPTS; $attempt++) {
            $this->submitCode($user, ['code' => $wrong])->assertSessionHasErrors('code');
        }

        $this->submitCode($user, ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_only_one_code_mail_per_minute(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $verification = app(EmailVerification::class);

        $this->assertTrue($verification->send($user));
        $this->assertFalse($verification->send($user), 'A second mail within the minute must be refused.');
        $this->assertGreaterThan(0, $verification->retryAfter($user));

        Notification::assertSentToTimes($user, VerifyEmailCode::class, 1);

        $this->withSession([EmailVerification::SESSION_KEY => $user->getKey()])
            ->from(route('verification.notice'))
            ->post('/email/verification-notification')
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors('code');

        Notification::assertSentToTimes($user, VerifyEmailCode::class, 1);

        $this->travel(EmailVerification::RESEND_INTERVAL_SECONDS + 1)->seconds();

        $this->withSession([EmailVerification::SESSION_KEY => $user->getKey()])
            ->from(route('verification.notice'))
            ->post('/email/verification-notification')
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'verification-code-sent');

        Notification::assertSentToTimes($user, VerifyEmailCode::class, 2);
    }

    public function test_a_resend_replaces_the_previous_code(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $first = $this->issueCode($user);

        $this->travel(EmailVerification::RESEND_INTERVAL_SECONDS + 1)->seconds();
        Notification::fake();
        $second = $this->issueCode($user);

        $this->assertNotSame($first, $second);

        $this->submitCode($user, ['code' => $first])->assertSessionHasErrors('code');
        $this->submitCode($user, ['code' => $second])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_an_expired_code_no_longer_verifies(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $code = $this->issueCode($user);

        $this->travel(EmailVerification::CODE_TTL_MINUTES + 1)->minutes();

        $this->submitCode($user, ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_verified_account_is_sent_to_login_instead_of_mailed_again(): void
    {
        Notification::fake();
        $this->requireVerification();
        $user = User::factory()->create();

        $this->withSession([EmailVerification::SESSION_KEY => $user->getKey()])
            ->get('/verify-email')
            ->assertRedirect(route('login'));

        $this->withSession([EmailVerification::SESSION_KEY => $user->getKey()])
            ->post('/email/verification-notification')
            ->assertRedirect(route('login'));

        Notification::assertNothingSent();
    }

    public function test_turning_the_switch_back_off_releases_a_waiting_account(): void
    {
        $this->requireVerification();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));

        $this->requireVerification(false);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        // Nothing left to verify, so the code page hands the visitor back to
        // login. (Logged out first: the `guest` middleware would otherwise send
        // an authenticated visitor to the dashboard before this even runs.)
        Auth::logout();

        $this->withSession([EmailVerification::SESSION_KEY => $user->getKey()])
            ->get('/verify-email')
            ->assertRedirect(route('login'));
    }

    public function test_password_reset_flow_is_untouched(): void
    {
        $this->get('/forgot-password')->assertOk();

        foreach (['password.request', 'password.email', 'password.reset', 'password.store'] as $name) {
            $this->assertTrue(Route::has($name), "Route [{$name}] is missing.");
        }
    }

    private function otherThan(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }
}
