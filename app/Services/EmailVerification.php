<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
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

    // Sending is synchronous. Keep the lease longer than the code's entire
    // lifetime, and never publish a send that outlived its lease.
    private const LOCK_SECONDS = self::CODE_TTL_MINUTES * 60 + self::RESEND_INTERVAL_SECONDS;

    private const LOCK_WAIT_SECONDS = 10;

    /**
     * Issue a fresh code and mail it, unless one went out less than a minute
     * ago. Returns false when another request is already sending, the account
     * no longer needs verification, or the resend budget said no.
     */
    public function send(User $user): bool
    {
        return $this->withAccountLock($user, function (Lock $lock) use ($user): bool {
            $fresh = $user->fresh();

            if ($fresh === null || $fresh->hasVerifiedEmail() || $this->retryAfter($fresh) > 0) {
                return false;
            }

            $code = (string) random_int(100000, 999999);
            $expiresAt = Carbon::now()->addMinutes(self::CODE_TTL_MINUTES);
            $payload = [
                'hash' => Hash::make($code),
                'email' => $fresh->email,
                'expires_at' => $expiresAt->getTimestamp(),
                'attempts' => 0,
            ];

            $fresh->notify(new VerifyEmailCode($code, self::CODE_TTL_MINUTES));

            if (! $lock->isOwnedByCurrentProcess() || $expiresAt->isPast()) {
                return false;
            }

            // Publish and count only after the mailer accepted the message. A
            // failed send leaves the previous code and resend budget intact.
            Cache::put($this->cacheKey($fresh), $payload, $expiresAt);
            RateLimiter::hit($this->throttleKey($fresh), self::RESEND_INTERVAL_SECONDS);

            return true;
        });
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
        return $this->withAccountLock($user, function () use ($user, $code): bool {
            $fresh = $user->fresh();

            return $fresh !== null && $this->consume($fresh, $code);
        });
    }

    /** Consume and verify the same locked, current email address. */
    public function verify(User $user, string $code): bool
    {
        $changed = false;
        $accepted = $this->withAccountLock($user, function () use ($user, $code, &$changed): bool {
            $connection = $user->getConnection();

            return $connection->transaction(function () use ($user, $code, &$changed, $connection): bool {
                if ($connection->getDriverName() === 'sqlite') {
                    // Reserve SQLite's write lock before reading the account
                    // and cache; unrelated writes cannot invalidate this read
                    // transaction when it later consumes the code.
                    $connection->table($user->getTable())->where($user->getKeyName(), $user->getKey())
                        ->update([$user->getKeyName() => $user->getKey()]);
                }

                $fresh = User::on($connection->getName())->lockForUpdate()->find($user->getKey());

                if ($fresh === null || ! $this->consume($fresh, $code)) {
                    return false;
                }

                if (! $fresh->hasVerifiedEmail()) {
                    $changed = $fresh->markEmailAsVerified();

                    if (! $changed) {
                        return false;
                    }
                }

                $user->setRawAttributes($fresh->getAttributes(), true);

                return true;
            }, attempts: 5);
        });

        if ($accepted && $changed) {
            event(new Verified($user));
        }

        return $accepted;
    }

    /** The caller must hold this account's shared lock. */
    private function consume(User $user, string $code): bool
    {
        $key = $this->cacheKey($user);
        $payload = Cache::get($key);

        if (! is_array($payload)
            || ($payload['email'] ?? null) !== $user->email
            || ($payload['expires_at'] ?? 0) <= now()->getTimestamp()) {
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
     * Issuance, attempt counting and consumption use the exact same per-account
     * key in the configured shared cache (database by default).
     *
     * @param  callable(Lock): bool  $operation
     */
    private function withAccountLock(User $user, callable $operation): bool
    {
        $lock = Cache::lock('email-verification-lock:'.$user->getKey(), self::LOCK_SECONDS);

        try {
            return $lock->block(self::LOCK_WAIT_SECONDS, fn () => $operation($lock));
        } catch (LockTimeoutException) {
            // Contention must not turn a verification form into a server error.
            // The current request does not alter the code or its attempt count.
            return false;
        }
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
