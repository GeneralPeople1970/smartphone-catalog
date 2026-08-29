<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * 用户管理 can set a user's email verification state by hand. It is the escape
 * hatch behind the registration verification switch: an address that can no
 * longer receive mail, or an account that predates the switch, is released from
 * here instead of by relabelling everybody at once.
 */
class UserEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_user_list_shows_the_verification_state_and_a_toggle(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->unverified()->create(['email' => 'pending@example.com']);
        User::factory()->create(['email' => 'done@example.com']);

        $response = $this->actingAs($owner)->get('/admin/users');

        $response->assertOk();
        $response->assertSee('邮箱未验证');
        $response->assertSee('邮箱已验证');
        $response->assertSee('标记已验证');
        $response->assertSee('取消验证');
    }

    public function test_an_owner_can_mark_an_address_verified(): void
    {
        $owner = User::factory()->owner()->create();
        $user = User::factory()->unverified()->create();

        $this->actingAs($owner)
            ->patch(route('users.email-verification', $user), ['verified' => '1'])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_an_owner_can_take_verification_back(): void
    {
        $owner = User::factory()->owner()->create();
        $user = User::factory()->create();

        $this->actingAs($owner)
            ->patch(route('users.email-verification', $user), ['verified' => '0'])
            ->assertRedirect(route('users.index'));

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_an_admin_may_only_manage_users_and_editors(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([User::factory()->unverified()->create(), User::factory()->editor()->unverified()->create()] as $target) {
            $this->actingAs($admin)
                ->patch(route('users.email-verification', $target), ['verified' => '1'])
                ->assertRedirect(route('users.index'));

            $this->assertNotNull($target->fresh()->email_verified_at);
        }

        $otherAdmin = User::factory()->admin()->unverified()->create();

        $this->actingAs($admin)
            ->patch(route('users.email-verification', $otherAdmin), ['verified' => '1'])
            ->assertForbidden();

        $this->assertNull($otherAdmin->fresh()->email_verified_at);
    }

    public function test_nobody_can_mark_their_own_address_verified(): void
    {
        // Otherwise the switch is decorative: every admin could walk past it.
        foreach ([User::factory()->admin()->unverified()->create(), User::factory()->owner()->unverified()->create()] as $actor) {
            $this->actingAs($actor)
                ->patch(route('users.email-verification', $actor), ['verified' => '1'])
                ->assertForbidden();

            $this->assertNull($actor->fresh()->email_verified_at);
        }
    }

    public function test_an_owners_verification_cannot_be_changed(): void
    {
        $owner = User::factory()->owner()->create();
        $otherOwner = User::factory()->owner()->unverified()->create();

        // Owners count as verified whatever the column says, so there is nothing
        // to toggle and no way to lock the last operator out.
        $this->assertTrue($otherOwner->hasVerifiedEmail());

        $this->actingAs($owner)
            ->patch(route('users.email-verification', $otherOwner), ['verified' => '0'])
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('users.email-verification', $otherOwner), ['verified' => '0'])
            ->assertForbidden();
    }

    public function test_editors_and_users_cannot_reach_the_route(): void
    {
        $target = User::factory()->unverified()->create();

        foreach ([User::factory()->create(), User::factory()->editor()->create()] as $actor) {
            $this->actingAs($actor)
                ->patch(route('users.email-verification', $target), ['verified' => '1'])
                ->assertForbidden();
        }

        $this->assertNull($target->fresh()->email_verified_at);
    }

    public function test_verified_is_required_and_must_be_a_boolean(): void
    {
        $owner = User::factory()->owner()->create();
        $user = User::factory()->unverified()->create();

        $this->actingAs($owner)
            ->from(route('users.index'))
            ->patch(route('users.email-verification', $user), [])
            ->assertSessionHasErrors('verified');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_marking_verified_lets_the_account_log_in_again(): void
    {
        SiteSettings::putBool(SiteSettings::REGISTRATION_EMAIL_VERIFICATION, true);

        $owner = User::factory()->owner()->create();
        $user = User::factory()->unverified()->create();

        $this->actingAs($owner)
            ->patch(route('users.email-verification', $user), ['verified' => '1'])
            ->assertRedirect(route('users.index'));

        Auth::logout();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user->fresh());
    }
}
