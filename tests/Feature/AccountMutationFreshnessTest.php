<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AccountMutationFreshnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_change_rechecks_an_actor_demoted_after_initial_authorization(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->afterAuthorization('updateRole', fn () => User::whereKey($actor)->update(['role' => 'user']));

        $this->actingAs($actor)
            ->patch(route('users.role', $target), ['role' => 'editor'])
            ->assertForbidden();

        $this->assertSame(UserRole::User, $target->fresh()->role);
    }

    public function test_status_change_rechecks_an_actor_suspended_after_initial_authorization(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->afterAuthorization('updateStatus', fn () => User::whereKey($actor)->update(['status' => 'suspended']));

        $this->actingAs($actor)
            ->patch(route('users.status', $target), ['status' => 'suspended'])
            ->assertForbidden();

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
    }

    public function test_role_change_rechecks_a_target_promoted_after_initial_authorization(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->afterAuthorization('updateRole', fn () => User::whereKey($target)->update(['role' => 'admin']));

        $this->actingAs($actor)
            ->patch(route('users.role', $target), ['role' => 'editor'])
            ->assertForbidden();

        $this->assertSame(UserRole::Admin, $target->fresh()->role);
    }

    public function test_status_change_rechecks_a_target_promoted_after_initial_authorization(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->afterAuthorization('updateStatus', fn () => User::whereKey($target)->update(['role' => 'admin']));

        $this->actingAs($actor)
            ->patch(route('users.status', $target), ['status' => 'suspended'])
            ->assertForbidden();

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
    }

    public function test_role_change_compares_with_the_locked_role_even_when_the_request_looked_unchanged(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->afterAuthorization('updateRole', fn () => User::whereKey($target)->update(['role' => 'editor']));

        $this->actingAs($actor)
            ->patch(route('users.role', $target), ['role' => 'user'])
            ->assertRedirect(route('users.index'));

        $this->assertSame(UserRole::User, $target->fresh()->role);
    }

    public function test_status_change_compares_with_the_locked_status_even_when_the_request_looked_unchanged(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->afterAuthorization('updateStatus', fn () => User::whereKey($target)->update(['status' => 'suspended']));

        $this->actingAs($actor)
            ->patch(route('users.status', $target), ['status' => 'active'])
            ->assertRedirect(route('users.index'));

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
    }

    public function test_email_verification_change_rechecks_a_target_promoted_after_initial_authorization(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->unverified()->create();
        $this->afterAuthorization('updateEmailVerification', fn () => User::whereKey($target)->update(['role' => 'admin']));

        $this->actingAs($actor)
            ->patch(route('users.email-verification', $target), ['verified' => '1'])
            ->assertForbidden();

        $this->assertNull($target->fresh()->email_verified_at);
    }

    public function test_email_change_clears_verification_completed_after_the_profile_snapshot_was_loaded(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        $user->fresh()->forceFill(['email_verified_at' => now()])->save();

        $this->patch('/profile', ['name' => $user->name, 'email' => 'replacement@example.com'])
            ->assertSessionHasNoErrors()->assertRedirect('/profile');

        $fresh = $user->fresh();
        $this->assertSame('replacement@example.com', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
    }

    public function test_an_unchanged_email_keeps_verification_completed_after_the_profile_snapshot_was_loaded(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        $user->fresh()->forceFill(['email_verified_at' => now()])->save();

        $this->patch('/profile', ['name' => 'Updated name', 'email' => $user->email])
            ->assertSessionHasNoErrors()->assertRedirect('/profile');

        $fresh = $user->fresh();
        $this->assertSame('Updated name', $fresh->name);
        $this->assertNotNull($fresh->email_verified_at);
    }

    /** Simulate a committed account change after the first policy decision. */
    private function afterAuthorization(string $ability, callable $change): void
    {
        $changed = false;

        Gate::after(function (User $actor, string $checkedAbility, ?bool $result) use ($ability, $change, &$changed): void {
            if (! $changed && $checkedAbility === $ability && $result) {
                $changed = true;
                $change();
            }
        });
    }
}
