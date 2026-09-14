<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Services\EmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class EmailVerificationStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stale_user_cannot_consume_a_code_for_a_replaced_email_address(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $verification = app(EmailVerification::class);
        $verification->send($user);
        $code = Notification::sent($user, VerifyEmailCode::class)->sole()->code;

        $user->fresh()->update(['email' => 'replacement@example.com']);

        $this->assertFalse($verification->check($user, $code));
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_deleted_account_cannot_consume_its_cached_code(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $verification = app(EmailVerification::class);
        $verification->send($user);
        $code = Notification::sent($user, VerifyEmailCode::class)->sole()->code;
        $user->fresh()->delete();

        $this->assertFalse($verification->check($user, $code));
    }

    public function test_wrong_attempts_keep_the_original_expiration(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $verification = app(EmailVerification::class);
        $verification->send($user);
        $payload = Cache::get('email-verification-code:'.$user->id);

        $this->travel(9)->minutes();
        $this->assertFalse($verification->check($user, '000000'));
        $updated = Cache::get('email-verification-code:'.$user->id);

        $this->assertSame($payload['expires_at'], $updated['expires_at']);
        $this->assertSame(1, $updated['attempts']);
        $this->travel(61)->seconds();
        $this->assertNull(Cache::get('email-verification-code:'.$user->id));
    }

    public function test_a_failed_notification_releases_the_account_for_an_immediate_retry(): void
    {
        $user = User::factory()->unverified()->create();
        $verification = app(EmailVerification::class);

        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('mail unavailable'));

        try {
            $verification->send($user);
            $this->fail('The simulated notification failure was not raised.');
        } catch (RuntimeException $e) {
            $this->assertSame('mail unavailable', $e->getMessage());
        }

        $this->assertSame(0, $verification->retryAfter($user));
        Notification::fake();
        $this->assertTrue($verification->send($user));
        Notification::assertSentToTimes($user, VerifyEmailCode::class, 1);
    }

    public function test_a_failed_resend_keeps_the_previous_code_usable(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $verification = app(EmailVerification::class);
        $verification->send($user);
        $code = Notification::sent($user, VerifyEmailCode::class)->sole()->code;
        $this->travel(EmailVerification::RESEND_INTERVAL_SECONDS + 1)->seconds();
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('mail unavailable'));

        try {
            $verification->send($user);
            $this->fail('The simulated notification failure was not raised.');
        } catch (RuntimeException $e) {
            $this->assertSame('mail unavailable', $e->getMessage());
        }

        $this->assertSame(0, $verification->retryAfter($user));
        $this->assertTrue($verification->check($user, $code));
    }
}
