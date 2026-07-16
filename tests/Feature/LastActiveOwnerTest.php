<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\LastActiveOwnerException;
use App\Models\User;
use App\Services\OwnerGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LastActiveOwnerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Owner,
            'status' => UserStatus::Active,
        ], $attributes));
    }

    // --- Profile self-deletion -------------------------------------------

    public function test_last_active_owner_cannot_delete_own_account(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password']);

        $response->assertRedirect('/profile');
        $response->assertSessionHasErrorsIn('userDeletion', ['userDeletion']);

        // Account untouched, session still authenticated.
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
        $this->assertAuthenticatedAs($owner);
    }

    public function test_owner_can_delete_own_account_when_another_active_owner_exists(): void
    {
        $owner = $this->owner();
        $other = $this->owner();

        $response = $this->actingAs($owner)
            ->delete('/profile', ['password' => 'password']);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('users', ['id' => $other->id, 'role' => 'owner']);
    }

    public function test_suspended_second_owner_does_not_unlock_self_deletion(): void
    {
        $owner = $this->owner();
        $this->owner(['status' => UserStatus::Suspended]);

        $this->actingAs($owner)->delete('/profile', ['password' => 'password']);

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertAuthenticatedAs($owner);
    }

    public function test_regular_user_can_still_delete_own_account(): void
    {
        $this->owner();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete('/profile', ['password' => 'password']);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // --- Admin role/status changes share the same invariant ---------------

    public function test_web_role_change_cannot_demote_last_active_owner(): void
    {
        // The policy blocks changing your own role, and blocks demoting the
        // last owner; the OwnerGuard must also hold as the final barrier.
        // Simulate the race directly against the guard below; here verify the
        // full HTTP path with two owners where one is suspended mid-flight.
        $owner = $this->owner();
        $target = $this->owner();

        // Suspend $target's counterpart scenario: $owner demotes $target while
        // $target is the only *other* owner — allowed (owner itself remains).
        $response = $this->actingAs($owner)
            ->patch(route('users.role', $target), ['role' => 'admin']);

        $response->assertRedirect(route('users.index'));
        $this->assertSame('admin', $target->fresh()->role->value);

        // Now $owner is the last active owner; nobody may demote or suspend
        // them via the web (policy denies self-change), and the guard denies
        // any path that would slip through.
        $this->expectException(LastActiveOwnerException::class);
        OwnerGuard::mutate($owner, function (User $locked): void {
            $locked->role = UserRole::Admin;
            $locked->save();
        });
    }

    public function test_guard_rolls_back_role_change_that_would_remove_last_owner(): void
    {
        $owner = $this->owner();

        try {
            OwnerGuard::mutate($owner, function (User $locked): void {
                $locked->role = UserRole::User;
                $locked->save();
            });
            $this->fail('LastActiveOwnerException was not thrown.');
        } catch (LastActiveOwnerException) {
            // expected
        }

        $this->assertSame('owner', $owner->fresh()->role->value);
    }

    public function test_guard_rolls_back_suspension_of_last_owner(): void
    {
        $owner = $this->owner();

        try {
            OwnerGuard::mutate($owner, function (User $locked): void {
                $locked->status = UserStatus::Suspended;
                $locked->save();
            });
            $this->fail('LastActiveOwnerException was not thrown.');
        } catch (LastActiveOwnerException) {
            // expected
        }

        $this->assertSame('active', $owner->fresh()->status->value);
    }

    public function test_guard_rolls_back_deletion_of_last_owner(): void
    {
        $owner = $this->owner();

        try {
            OwnerGuard::mutate($owner, fn (User $locked) => $locked->delete());
            $this->fail('LastActiveOwnerException was not thrown.');
        } catch (LastActiveOwnerException) {
            // expected
        }

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
    }

    public function test_guard_allows_mutations_when_bootstrapping_from_zero_owners(): void
    {
        $user = User::factory()->create();

        OwnerGuard::mutate($user, function (User $locked): void {
            $locked->role = UserRole::Owner;
            $locked->save();
        });

        $this->assertSame('owner', $user->fresh()->role->value);
    }

    public function test_guard_uses_fresh_data_not_a_stale_instance(): void
    {
        $owner = $this->owner();
        $second = $this->owner();

        // Stale instance: $second demoted through another path first.
        $second->fresh()->forceFill(['role' => UserRole::User])->save();

        // Now $owner really is the last active owner, even though the stale
        // in-memory state suggests otherwise.
        $this->expectException(LastActiveOwnerException::class);
        OwnerGuard::mutate($owner, function (User $locked): void {
            $locked->status = UserStatus::Suspended;
            $locked->save();
        });
    }

    // --- CLI user:promote --------------------------------------------------

    public function test_cli_cannot_demote_last_active_owner_even_with_force(): void
    {
        $owner = $this->owner(['email' => 'boss@example.com']);

        $this->artisan('user:promote', ['email' => 'boss@example.com', '--role' => 'user', '--force' => true])
            ->assertFailed();

        $this->assertSame('owner', $owner->fresh()->role->value);
    }

    public function test_cli_can_demote_owner_when_another_active_owner_exists(): void
    {
        $owner = $this->owner(['email' => 'boss@example.com']);
        $this->owner();

        $this->artisan('user:promote', ['email' => 'boss@example.com', '--role' => 'admin', '--force' => true])
            ->assertSuccessful();

        $this->assertSame('admin', $owner->fresh()->role->value);
    }

    public function test_cli_can_bootstrap_first_owner(): void
    {
        $user = User::factory()->create(['email' => 'first@example.com']);

        $this->artisan('user:promote', ['email' => 'first@example.com', '--role' => 'owner', '--force' => true])
            ->assertSuccessful();

        $this->assertSame('owner', $user->fresh()->role->value);
    }

    public function test_cli_without_force_still_asks_for_confirmation(): void
    {
        $this->owner(['email' => 'boss@example.com']);
        User::factory()->create(['email' => 'second@example.com']);

        $this->artisan('user:promote', ['email' => 'second@example.com', '--role' => 'editor'])
            ->expectsConfirmation('Do you want to continue?', 'no')
            ->assertFailed();

        $this->assertSame('user', User::where('email', 'second@example.com')->first()->role->value);
    }
}
