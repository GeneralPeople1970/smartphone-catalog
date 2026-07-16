<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EmailVerificationRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_routes_no_longer_exist(): void
    {
        foreach (['verification.notice', 'verification.verify', 'verification.send'] as $name) {
            $this->assertFalse(Route::has($name), "Route [{$name}] should have been removed.");
        }

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/verify-email')->assertNotFound();
        $this->actingAs($user)->post('/email/verification-notification')->assertNotFound();
    }

    public function test_unverified_user_is_not_redirected_into_a_verification_flow(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
    }

    public function test_unverified_regular_user_lands_on_profile_after_login(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/profile');
    }

    public function test_unverified_editor_can_still_access_backend(): void
    {
        $editor = User::factory()->editor()->unverified()->create();

        $this->actingAs($editor)->get('/dashboard')->assertOk();
    }

    public function test_password_reset_flow_is_still_available(): void
    {
        $this->get('/forgot-password')->assertOk();

        $this->assertTrue(Route::has('password.request'));
        $this->assertTrue(Route::has('password.email'));
        $this->assertTrue(Route::has('password.reset'));
        $this->assertTrue(Route::has('password.store'));
    }
}
