<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\LastActiveOwnerException;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Central enforcement of the "the system always keeps at least one active
 * owner" invariant. Every mutation that can change a user's role, status or
 * existence must go through {@see mutate()} so the check cannot be bypassed
 * or raced: the mutation runs inside a transaction, all active-owner rows are
 * locked first (serializing concurrent owner mutations on MySQL), and the
 * invariant is re-verified after the mutation but before commit.
 *
 * Bootstrapping stays possible: when the system currently has zero active
 * owners (fresh install), any mutation is allowed, so promoting the first
 * owner via `user:promote` keeps working.
 */
class OwnerGuard
{
    /**
     * Run $mutation against a freshly locked copy of $target inside a
     * transaction, enforcing the last-active-owner invariant.
     *
     * @template TReturn
     *
     * @param  callable(User): TReturn  $mutation
     * @return TReturn
     *
     * @throws LastActiveOwnerException if the mutation would leave zero active owners
     */
    public static function mutate(User $target, callable $mutation): mixed
    {
        return DB::transaction(function () use ($target, $mutation) {
            // Locking read: serializes concurrent owner mutations on MySQL
            // (SQLite ignores FOR UPDATE; its writes serialize on their own).
            $hadActiveOwner = self::activeOwnerCount() > 0;

            $fresh = User::query()->lockForUpdate()->find($target->getKey());

            if ($fresh === null) {
                throw (new ModelNotFoundException)->setModel(User::class, [$target->getKey()]);
            }

            $result = $mutation($fresh);

            if ($hadActiveOwner && self::activeOwnerCount() === 0) {
                throw new LastActiveOwnerException;
            }

            // Keep the caller's instance in sync with what was committed.
            if ($fresh->exists) {
                $target->setRawAttributes($fresh->getAttributes(), true);
            } else {
                $target->exists = false;
            }

            return $result;
        });
    }

    /**
     * Count active owners with a locking read (inside the transaction).
     */
    private static function activeOwnerCount(): int
    {
        return User::query()
            ->where('role', UserRole::Owner->value)
            ->where('status', UserStatus::Active->value)
            ->lockForUpdate()
            ->count();
    }
}
