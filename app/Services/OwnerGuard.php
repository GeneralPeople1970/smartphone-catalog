<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\LastActiveOwnerException;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
     * @param  callable(User, ?User): TReturn  $mutation
     * @param  User|null  $actor  Lock and reload the actor before reauthorizing in $mutation.
     * @return TReturn
     *
     * @throws LastActiveOwnerException if the mutation would leave zero active owners
     */
    public static function mutate(User $target, callable $mutation, ?User $actor = null): mixed
    {
        $connection = $target->getConnection();
        [$result, $fresh, $freshActor] = $connection->transaction(function () use ($target, $mutation, $actor, $connection) {
            if ($connection->getDriverName() === 'sqlite') {
                // FOR UPDATE is ignored by SQLite. Acquire its write lock
                // before reading so concurrent requests do not both read an
                // old owner set and then fail to upgrade a deferred read lock.
                $connection->table($target->getTable())
                    ->where($target->getKeyName(), $target->getKey())
                    ->update([$target->getKeyName() => $target->getKey()]);
            }

            $hadActiveOwner = self::activeOwnerCount($connection->getName()) > 0;
            $ids = array_filter([$target->getKey(), $actor?->getKey()], fn ($id) => $id !== null);
            $users = User::on($connection->getName())->whereKey($ids)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $fresh = $users->get($target->getKey());
            $freshActor = $actor === null ? null : $users->get($actor->getKey());

            if ($fresh === null || ($actor !== null && $freshActor === null)) {
                throw (new ModelNotFoundException)->setModel(User::class, $ids);
            }

            $result = $mutation($fresh, $freshActor);

            if ($hadActiveOwner && self::activeOwnerCount($connection->getName()) === 0) {
                throw new LastActiveOwnerException;
            }

            return [$result, $fresh, $freshActor];
        }, attempts: 5);

        // Synchronize only after commit: a rollback or deadlock retry must not
        // leave the caller holding a state that was never persisted.
        $target->setRawAttributes($fresh->getAttributes(), true);
        $target->exists = $fresh->exists;

        if ($actor !== null && $freshActor !== null) {
            $actor->setRawAttributes($freshActor->getAttributes(), true);
            $actor->exists = $freshActor->exists;
        }

        return $result;
    }

    /**
     * Count active owners with a locking read (inside the transaction).
     */
    private static function activeOwnerCount(string $connection): int
    {
        return User::on($connection)
            ->where('role', UserRole::Owner->value)
            ->where('status', UserStatus::Active->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id'])
            ->count();
    }
}
