<?php

namespace Tests\Support;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Shared configuration for real, isolated parent/worker database connections. */
final class AccountConcurrencyEnvironment
{
    public static function configure(array $connection, string $prefix): void
    {
        config([
            'database.default' => 'account_concurrency',
            'database.connections.account_concurrency' => $connection,
            'cache.default' => 'database',
            'cache.prefix' => 'account-concurrency:'.$prefix.':',
            'cache.stores.database.connection' => 'account_concurrency',
            'cache.stores.database.lock_connection' => 'account_concurrency',
            'cache.stores.database.table' => 'cache',
            'cache.stores.database.lock_table' => 'cache_locks',
            'session.driver' => 'array',
            'mail.default' => 'array',
            'mail.from.address' => 'account-tests@example.test',
            'mail.from.name' => 'Account concurrency tests',
            'hashing.bcrypt.rounds' => 4,
        ]);

        DB::purge('account_concurrency');
        DB::setDefaultConnection('account_concurrency');
        Cache::forgetDriver('database');
        app()->forgetInstance(RateLimiter::class);
        \Illuminate\Support\Facades\RateLimiter::clearResolvedInstance(RateLimiter::class);
    }

    public static function waitFor(string $path, float $seconds = 15): void
    {
        $deadline = microtime(true) + $seconds;

        while (! is_file($path)) {
            if (microtime(true) >= $deadline) {
                throw new \RuntimeException('Timed out waiting for test coordination file: '.basename($path));
            }

            usleep(10000);
            clearstatcache(true, $path);
        }
    }
}
