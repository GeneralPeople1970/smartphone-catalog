<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_no_accounts_in_production(): void
    {
        // Simulate production: the seeder must be a no-op for user accounts.
        // (Invoked directly — artisan db:seed would additionally stop at the
        // interactive "running in production" confirmation.)
        app()->detectEnvironment(fn () => 'production');

        app(DatabaseSeeder::class)->run();

        $this->assertSame(0, User::count());
    }

    public function test_seeder_creates_test_account_in_testing(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, User::count());
    }
}
