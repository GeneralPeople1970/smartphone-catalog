<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Email verification by code instead of by signed link.
 *
 * Two things live here, because both are shared by registration, login and the
 * resend button and neither belongs in a controller:
 *
 * - the code itself: issued into the cache with a TTL (so it expires without a
 *   cleanup job), mailed, and checked back;
 * - the handoff: while verification is required an unverified account never
 *   holds a session, so the pending account is remembered by id in the session
 *   of whoever just proved the password — at registration, or at a login that
 *   was turned away.
 *
 * The one-mail-per-minute budget is enforced in send(), the single place any
 * verification mail goes out from, using the framework rate limiter.
 */
class EmailVerification
{
    /**
     * Session key holding the id of the account currently being verified.
     */
    public const SESSION_KEY = 'email_verification.user_id';

    public const CODE_TTL_MINUTES = 10;

    public const RESEND_INTERVAL_SECONDS = 60;

    /**
     * Wrong codes tolerated per issued code. A 6-digit code plus route
     * throttling already makes guessing hopeless from one address; this closes
     * the same door for a distributed guesser.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * Issue a fresh code and mail it, unless one went out less than a minute
     * ago. Returns false when the budget said no and nothing was sent.
     */
    public function send(User $user): bool
    {
        if ($this->retryAfter($user) > 0) {
            return false;
        }

        $code = (string) random_int(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(self::CODE_TTL_MINUTES);

        Cache::put($this->cacheKey($user), [
            'hash' => Hash::make($code),
            // Bound to the address the code was mailed to: changing the address
            // must not leave an older code usable.
            'email' => $user->email,
            'expires_at' => $expiresAt->getTimestamp(),
            'attempts' => 0,
        ], $expiresAt);

        $user->notify(new VerifyEmailCode($code, self::CODE_TTL_MINUTES));

        // Counted only once the mailer accepted the message: a failed send must
        // not cost the account its next minute.
        RateLimiter::hit($this->throttleKey($user), self::RESEND_INTERVAL_SECONDS);

        return true;
    }

    /**
     * Seconds until another mail may be sent; 0 when one may go out now.
     */
    public function retryAfter(User $user): int
    {
        return RateLimiter::availableIn($this->throttleKey($user));
    }

    /**
     * Check a submitted code. Consumes the code on success, and burns it after
     * too many wrong tries.
     */
    public function check(User $user, string $code): bool
    {
        $key = $this->cacheKey($user);
        $payload = Cache::get($key);

        if (! is_array($payload) || ($payload['email'] ?? null) !== $user->email) {
            return false;
        }

        if (! Hash::check($code, (string) ($payload['hash'] ?? ''))) {
            $payload['attempts'] = (int) ($payload['attempts'] ?? 0) + 1;

            if ($payload['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget($key);
            } else {
                // Re-stored against the original expiry, so a wrong guess never
                // buys the code more time.
                Cache::put($key, $payload, Carbon::createFromTimestamp($payload['expires_at']));
            }

            return false;
        }

        Cache::forget($key);

        return true;
    }

    /**
     * Remember which account is waiting for a code in the current session.
     */
    public function remember(User $user): void
    {
        session([self::SESSION_KEY => $user->getKey()]);
    }

    /**
     * The account this session is verifying, if any.
     */
    public function pending(): ?User
    {
        $id = session(self::SESSION_KEY);

        return $id === null ? null : User::query()->find($id);
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    private function cacheKey(User $user): string
    {
        return 'email-verification-code:'.$user->getKey();
    }

    private function throttleKey(User $user): string
    {
        return 'email-verification-send:'.$user->getKey();
    }
}
