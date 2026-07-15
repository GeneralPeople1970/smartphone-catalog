<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_test_user_in_non_production(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_seeder_does_not_create_a_test_user_in_production(): void
    {
        $this->app['env'] = 'production';

        // --force bypasses Laravel's own production confirmation so we exercise
        // the seeder's environment guard directly.
        $this->artisan('db:seed', ['--force' => true]);

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
