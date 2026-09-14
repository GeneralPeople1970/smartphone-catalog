<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EmailVerification;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\AccountConcurrencyEnvironment;
use Tests\TestCase;

/**
 * Real PHP processes share the database cache and lock tables. By default each
 * test owns an on-disk SQLite database. To also exercise MySQL row locking, set
 * CONCURRENCY_DB_CONNECTION=mysql and the normal DB_* connection variables.
 * Random table prefixes isolate this suite; existing tables are never cleared.
 */
class AccountConcurrencyTest extends TestCase
{
    private string $directory;

    private bool $configured = false;

    /** @var array<string, Process> */
    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/smartphone-account-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);

        $driver = getenv('CONCURRENCY_DB_CONNECTION') ?: 'sqlite';
        $this->assertContains($driver, ['sqlite', 'mysql', 'mariadb']);
        $connection = config('database.connections.'.$driver);
        $prefix = 'acct_'.bin2hex(random_bytes(5)).'_';
        $connection['prefix'] = $prefix;
        $connection['prefix_indexes'] = true;
        // An inherited DB_URL must never route workers to a different schema.
        $connection['url'] = null;

        if ($driver === 'sqlite') {
            $connection['database'] = $this->directory.'/database.sqlite';
            $connection['busy_timeout'] = 10000;
            $connection['journal_mode'] = 'WAL';
            touch($connection['database']);
        }

        AccountConcurrencyEnvironment::configure($connection, $prefix);
        $this->configured = true;
        file_put_contents($this->directory.'/connection.json', json_encode([
            'connection' => $connection,
            'prefix' => $prefix,
        ], JSON_THROW_ON_ERROR));

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '0001_01_01_000001_create_cache_table.php',
            '2026_07_14_000001_add_role_and_status_to_users_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }

            if ($this->configured) {
                // These are the exact tables created with this test's random
                // prefix. No migrate:fresh, flush, or database-wide cleanup.
                foreach (['sessions', 'password_reset_tokens', 'users', 'cache', 'cache_locks'] as $table) {
                    Schema::dropIfExists($table);
                }
                DB::disconnect('account_concurrency');
            }

            if (isset($this->directory)) {
                (new Filesystem)->deleteDirectory($this->directory);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_simultaneous_issuance_sends_only_one_email(): void
    {
        $user = User::factory()->unverified()->create();
        $jobs = array_fill(0, 4, ['operation' => 'send', 'user_id' => $user->id]);

        $results = $this->runTogether($jobs);

        $this->assertSame(1, count(array_filter($results, fn ($result) => $result === true)));
        $mail = $this->mail();
        $this->assertCount(1, $mail);
        $this->assertTrue(app(EmailVerification::class)->check($user, $mail[0]['code']));
        $this->assertGreaterThan(0, app(EmailVerification::class)->retryAfter($user));
    }

    public function test_simultaneous_wrong_attempts_burn_the_code_without_lost_increments(): void
    {
        $user = User::factory()->unverified()->create();
        $this->putCode($user);
        $jobs = array_fill(0, EmailVerification::MAX_ATTEMPTS, [
            'operation' => 'check', 'user_id' => $user->id, 'code' => '000000',
        ]);

        $this->assertSame(array_fill(0, EmailVerification::MAX_ATTEMPTS, false), $this->runTogether($jobs));
        $this->assertFalse(app(EmailVerification::class)->check($user, '123456'));
        $this->assertNull(Cache::get('email-verification-code:'.$user->id));
    }

    public function test_simultaneous_correct_checks_consume_a_code_only_once(): void
    {
        $user = User::factory()->unverified()->create();
        $this->putCode($user);
        $jobs = array_fill(0, 3, ['operation' => 'check', 'user_id' => $user->id, 'code' => '123456']);

        $results = $this->runTogether($jobs);

        $this->assertSame(1, count(array_filter($results, fn ($result) => $result === true)));
        $this->assertFalse(app(EmailVerification::class)->check($user, '123456'));
    }

    public function test_simultaneous_verification_accepts_only_one_request_and_updates_the_account(): void
    {
        $user = User::factory()->unverified()->create();
        $this->putCode($user);
        $jobs = array_fill(0, 3, ['operation' => 'verify', 'user_id' => $user->id, 'code' => '123456']);

        $results = $this->runTogether($jobs);

        $this->assertSame(1, count(array_filter($results, fn ($result) => $result === true)));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_consuming_an_old_code_does_not_delete_a_concurrent_resend(): void
    {
        $user = User::factory()->unverified()->create();
        $this->putCode($user);
        $this->start('checking', [
            'operation' => 'check', 'user_id' => $user->id, 'code' => '123456',
            'pause_after_code_read' => true,
        ], 'check');
        $this->release('check', ['checking']);
        AccountConcurrencyEnvironment::waitFor($this->directory.'/checking.paused');

        $this->start('sending', ['operation' => 'send', 'user_id' => $user->id], 'send');
        $this->release('send', ['sending']);
        AccountConcurrencyEnvironment::waitFor($this->directory.'/sending.started');
        // A sender without the shared lock finishes while the old checker is
        // paused, exposing its subsequent deletion of the replacement code.
        usleep(500000);
        touch($this->directory.'/checking.resume');

        $this->assertTrue($this->finish('checking'));
        $this->assertTrue($this->finish('sending'));
        $mail = $this->mail();
        $this->assertCount(1, $mail);
        $this->assertTrue(app(EmailVerification::class)->check($user, $mail[0]['code']));
    }

    public function test_a_paused_check_does_not_block_issuance_for_another_account(): void
    {
        $first = User::factory()->unverified()->create();
        $second = User::factory()->unverified()->create();
        $this->putCode($first);
        $this->start('checking', [
            'operation' => 'check', 'user_id' => $first->id, 'code' => '123456',
            'pause_after_code_read' => true,
        ], 'check');
        $this->release('check', ['checking']);
        AccountConcurrencyEnvironment::waitFor($this->directory.'/checking.paused');

        $this->start('sending', ['operation' => 'send', 'user_id' => $second->id], 'send');
        $this->release('send', ['sending']);
        $this->assertTrue($this->finish('sending'));
        touch($this->directory.'/checking.resume');

        $this->assertTrue($this->finish('checking'));
        $this->assertSame($second->id, $this->mail()[0]['user_id']);
    }

    public function test_verification_survives_an_overlapping_account_write(): void
    {
        $verifying = User::factory()->unverified()->create();
        $other = User::factory()->create();
        $this->putCode($verifying);
        $this->start('verifying', [
            'operation' => 'verify', 'user_id' => $verifying->id, 'code' => '123456',
            'pause_after_code_read' => true,
        ], 'verify');
        $this->release('verify', ['verifying']);
        AccountConcurrencyEnvironment::waitFor($this->directory.'/verifying.paused');

        $this->start('updating', [
            'operation' => 'mutate', 'user_id' => $other->id, 'field' => 'role', 'value' => 'editor',
        ], 'update');
        $this->release('update', ['updating']);
        AccountConcurrencyEnvironment::waitFor($this->directory.'/updating.started');
        usleep(50000);
        touch($this->directory.'/verifying.resume');

        $this->assertTrue($this->finish('verifying'));
        $this->assertSame('changed', $this->finish('updating'));
        $this->assertNotNull($verifying->fresh()->email_verified_at);
    }

    public function test_an_email_change_waits_for_verification_and_clears_the_new_timestamp(): void
    {
        $user = User::factory()->unverified()->create();
        $this->putCode($user);
        // Load the profile's unverified user snapshot before verification; an
        // assignment of null to that stale model is not considered dirty.
        $this->start('profile', [
            'operation' => 'profile', 'user_id' => $user->id, 'email' => 'replacement@example.test',
        ], 'profile');
        AccountConcurrencyEnvironment::waitFor($this->directory.'/profile.ready');

        $this->start('verifying', [
            'operation' => 'verify', 'user_id' => $user->id, 'code' => '123456',
            'pause_after_code_read' => true,
        ], 'verify');
        $this->release('verify', ['verifying']);
        AccountConcurrencyEnvironment::waitFor($this->directory.'/verifying.paused');
        $this->release('profile', ['profile']);
        AccountConcurrencyEnvironment::waitFor($this->directory.'/profile.started');
        usleep(50000);
        touch($this->directory.'/verifying.resume');

        $this->assertTrue($this->finish('verifying'));
        $this->assertSame(302, $this->finish('profile'));
        $fresh = $user->fresh();
        $this->assertSame('replacement@example.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
    }

    public function test_an_email_changed_before_verification_rejects_the_old_address_code(): void
    {
        $user = User::factory()->unverified()->create();
        $this->putCode($user);
        $this->start('verifying', [
            'operation' => 'verify', 'user_id' => $user->id, 'code' => '123456',
        ], 'verify');
        AccountConcurrencyEnvironment::waitFor($this->directory.'/verifying.ready');

        $this->start('profile', [
            'operation' => 'profile', 'user_id' => $user->id, 'email' => 'replacement@example.test',
        ], 'profile');
        $this->release('profile', ['profile']);
        $this->assertSame(302, $this->finish('profile'));
        $this->release('verify', ['verifying']);

        $this->assertFalse($this->finish('verifying'));
        $fresh = $user->fresh();
        $this->assertSame('replacement@example.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
    }

    #[DataProvider('ownerRemovals')]
    public function test_concurrent_owner_removals_keep_one_active_owner(string $field, string $value): void
    {
        $first = User::factory()->owner()->create();
        $second = User::factory()->owner()->create();
        $jobs = array_map(fn (User $user) => [
            'operation' => 'mutate', 'user_id' => $user->id, 'field' => $field, 'value' => $value,
        ], [$first, $second]);

        $results = $this->runTogether($jobs);
        sort($results);

        $this->assertSame(['changed', 'last-owner'], $results);
        $this->assertSame(1, User::where('role', 'owner')->where('status', 'active')->count());
    }

    public static function ownerRemovals(): array
    {
        return [['role', 'user'], ['status', 'suspended'], ['delete', '']];
    }

    #[DataProvider('crossOwnerChanges')]
    public function test_owners_cannot_use_stale_permissions_to_demote_or_suspend_each_other(string $field, string $value): void
    {
        $first = User::factory()->owner()->create();
        $second = User::factory()->owner()->create();

        foreach ([[$first, $second], [$second, $first]] as $index => [$actor, $target]) {
            $this->start('owner-'.$index, [
                'operation' => 'controller', 'actor_id' => $actor->id, 'user_id' => $target->id,
                'field' => $field, 'value' => $value, 'pause_after_authorization' => true,
            ], 'owners');
        }
        $this->release('owners', ['owner-0', 'owner-1']);
        foreach (['owner-0', 'owner-1'] as $name) {
            AccountConcurrencyEnvironment::waitFor($this->directory.'/'.$name.'.paused');
        }
        foreach (['owner-0', 'owner-1'] as $name) {
            touch($this->directory.'/'.$name.'.resume');
        }

        $results = [$this->finish('owner-0'), $this->finish('owner-1')];
        sort($results);
        $this->assertSame([302, 403], $results);
        $this->assertSame(1, User::where('role', 'owner')->where('status', 'active')->count());
    }

    public static function crossOwnerChanges(): array
    {
        return [['role', 'user'], ['status', 'suspended']];
    }

    #[DataProvider('permissionChanges')]
    public function test_a_concurrent_permission_change_is_rechecked_before_writing(
        string $field,
        string $value,
        bool $changeActor,
        string $requestedField,
        string $requestedValue,
    ): void {
        User::factory()->owner()->create();
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->start('request', [
            'operation' => 'controller', 'actor_id' => $actor->id, 'user_id' => $target->id,
            'field' => $requestedField, 'value' => $requestedValue, 'pause_after_authorization' => true,
        ], 'request');
        $this->release('request', ['request']);
        AccountConcurrencyEnvironment::waitFor($this->directory.'/request.paused');

        $this->start('revocation', [
            'operation' => 'mutate', 'user_id' => $changeActor ? $actor->id : $target->id,
            'field' => $field, 'value' => $value,
        ], 'revocation');
        $this->release('revocation', ['revocation']);
        $this->assertSame('changed', $this->finish('revocation'));
        touch($this->directory.'/request.resume');

        $this->assertSame(403, $this->finish('request'));
        $fresh = $target->fresh();
        $this->assertSame($changeActor ? 'user' : 'admin', $fresh->role->value);
        $this->assertSame('active', $fresh->status->value);
    }

    public static function permissionChanges(): array
    {
        return [
            'demoted actor changes role' => ['role', 'user', true, 'role', 'editor'],
            'suspended actor changes status' => ['status', 'suspended', true, 'status', 'suspended'],
            'promoted target changes role' => ['role', 'admin', false, 'role', 'editor'],
            'promoted target changes status' => ['role', 'admin', false, 'status', 'suspended'],
        ];
    }

    private function putCode(User $user): void
    {
        $expires = now()->addMinutes(EmailVerification::CODE_TTL_MINUTES);
        Cache::put('email-verification-code:'.$user->id, [
            'hash' => Hash::make('123456', ['rounds' => 9]),
            'email' => $user->email,
            'expires_at' => $expires->getTimestamp(),
            'attempts' => 0,
        ], $expires);
    }

    private function runTogether(array $jobs): array
    {
        $names = [];
        foreach ($jobs as $index => $job) {
            $names[] = $name = 'worker-'.$index;
            $this->start($name, $job, 'together');
        }
        $this->release('together', $names);

        return array_map(fn ($name) => $this->finish($name), $names);
    }

    private function start(string $name, array $job, string $barrier): void
    {
        $process = new Process([
            PHP_BINARY,
            base_path('tests/Support/account-concurrency-worker.php'),
            $this->directory.'/connection.json',
            base64_encode(json_encode($job + ['worker' => $name, 'barrier' => $barrier], JSON_THROW_ON_ERROR)),
        ], base_path(), ['APP_ENV' => 'testing'], timeout: 30);
        $this->workers[$name] = $process;
        $process->start();
    }

    private function release(string $barrier, array $names): void
    {
        foreach ($names as $name) {
            AccountConcurrencyEnvironment::waitFor($this->directory.'/'.$name.'.ready');
        }
        touch($this->directory.'/'.$barrier.'.go');
    }

    private function finish(string $name): mixed
    {
        $worker = $this->workers[$name];
        $worker->wait();
        $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput().$worker->getOutput());

        return json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR)['result'];
    }

    private function mail(): array
    {
        return array_map(fn ($line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            file($this->directory.'/mail.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }
}
