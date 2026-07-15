<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\HomepageFeaturedPhone;
use App\Models\HomepageSlide;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    // 1. A freshly registered account is always a plain, active user.
    public function test_new_registered_account_is_a_plain_active_user(): void
    {
        $this->post('/register', [
            'name' => 'Plain User',
            'email' => 'plain@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('profile.edit', absolute: false));

        $user = User::query()->where('email', 'plain@example.com')->firstOrFail();

        $this->assertSame(UserRole::User, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
    }

    // 2. Registration must ignore any injected role/status in the request.
    public function test_registration_ignores_injected_role_and_status(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky User',
            'email' => 'sneaky@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'owner',
            'status' => 'suspended',
        ]);

        $user = User::query()->where('email', 'sneaky@example.com')->firstOrFail();

        $this->assertSame(UserRole::User, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
    }

    // 3. Guests cannot reach the backend.
    public function test_guests_are_redirected_from_the_backend(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/products')->assertRedirect(route('login'));
        $this->get('/admin/homepage')->assertRedirect(route('login'));
        $this->get('/admin/homepage-slides')->assertRedirect(route('login'));
        $this->get('/admin/users')->assertRedirect(route('login'));
    }

    // 4. A plain user is forbidden from every admin route (reads and writes).
    public function test_plain_users_are_forbidden_from_admin_routes(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertForbidden();
        $this->get('/admin/products')->assertForbidden();
        $this->post('/admin/products', [])->assertForbidden();
        $this->post('/admin/products/import', [])->assertForbidden();
        $this->get('/admin/homepage')->assertForbidden();
        $this->post('/admin/homepage/featured-phones', [])->assertForbidden();
        $this->get('/admin/homepage-slides')->assertForbidden();
        $this->post('/admin/homepage-slides', [])->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    // 5. An editor can manage products, slides, and featured phones.
    public function test_editor_can_manage_catalog_content(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->editor()->create());

        $this->get('/dashboard')->assertOk();
        $this->get(route('products.index'))->assertOk();
        $this->get(route('products.import'))->assertOk();

        $this->post(route('products.store'), [
            'brand' => 'Apple',
            'name' => 'Editor Phone',
            'status' => 'draft',
        ])->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', ['name' => 'Editor Phone']);

        $this->post(route('homepage-slides.store'), [
            'title' => 'Editor Slide',
            'image' => UploadedFile::fake()->image('slide.png'),
            'is_active' => '1',
        ])->assertRedirect(route('homepage-slides.index'));
        $this->assertDatabaseHas('homepage_slides', ['title' => 'Editor Slide']);

        $product = $this->createProduct();
        $this->post(route('homepage.featured-phones.store'), [
            'product_id' => $product->id,
        ])->assertRedirect(route('homepage.index'));
        $this->assertDatabaseHas('homepage_featured_phones', ['product_id' => $product->id]);
    }

    // 6. An editor cannot reach the user-management area.
    public function test_editor_cannot_access_user_management(): void
    {
        $this->actingAs(User::factory()->editor()->create());

        $this->get(route('users.index'))->assertForbidden();

        $target = User::factory()->create();
        $this->patch(route('users.role', $target), ['role' => 'editor'])->assertForbidden();
        $this->patch(route('users.status', $target), ['status' => 'suspended'])->assertForbidden();
    }

    // 7. An admin can manage users and editors.
    public function test_admin_can_manage_users_and_editors(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('users.index'))->assertOk();

        $user = User::factory()->create();
        $this->patch(route('users.role', $user), ['role' => 'editor'])
            ->assertRedirect(route('users.index'));
        $this->assertSame(UserRole::Editor, $user->refresh()->role);

        $editor = User::factory()->editor()->create();
        $this->patch(route('users.status', $editor), ['status' => 'suspended'])
            ->assertRedirect(route('users.index'));
        $this->assertTrue($editor->refresh()->isSuspended());

        $this->patch(route('users.status', $editor), ['status' => 'active'])
            ->assertRedirect(route('users.index'));
        $this->assertTrue($editor->refresh()->isActive());

        // Admin may also demote an editor back to a plain user.
        $this->patch(route('users.role', $editor), ['role' => 'user'])
            ->assertRedirect(route('users.index'));
        $this->assertSame(UserRole::User, $editor->refresh()->role);
    }

    // 8. An admin cannot modify admins or owners, nor grant admin/owner.
    public function test_admin_cannot_modify_admins_or_owners(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $otherAdmin = User::factory()->admin()->create();
        $this->patch(route('users.role', $otherAdmin), ['role' => 'editor'])->assertForbidden();
        $this->patch(route('users.status', $otherAdmin), ['status' => 'suspended'])->assertForbidden();
        $this->assertSame(UserRole::Admin, $otherAdmin->refresh()->role);
        $this->assertTrue($otherAdmin->refresh()->isActive());

        $owner = User::factory()->owner()->create();
        $this->patch(route('users.status', $owner), ['status' => 'suspended'])->assertForbidden();
        $this->patch(route('users.role', $owner), ['role' => 'editor'])->assertForbidden();
        $this->assertTrue($owner->refresh()->isActive());
        $this->assertTrue($owner->refresh()->isOwner());

        $user = User::factory()->create();
        $this->patch(route('users.role', $user), ['role' => 'admin'])->assertForbidden();
        $this->patch(route('users.role', $user), ['role' => 'owner'])->assertForbidden();
        $this->assertSame(UserRole::User, $user->refresh()->role);
    }

    // 9. An owner can grant admin.
    public function test_owner_can_grant_admin(): void
    {
        $this->actingAs(User::factory()->owner()->create());

        $editor = User::factory()->editor()->create();
        $this->patch(route('users.role', $editor), ['role' => 'admin'])
            ->assertRedirect(route('users.index'));
        $this->assertSame(UserRole::Admin, $editor->refresh()->role);

        // ...and revoke admin again (admin -> editor).
        $this->patch(route('users.role', $editor), ['role' => 'editor'])
            ->assertRedirect(route('users.index'));
        $this->assertSame(UserRole::Editor, $editor->refresh()->role);
    }

    // 10. The last active owner cannot be suspended, deleted, or demoted.
    public function test_last_active_owner_is_protected(): void
    {
        $owner = User::factory()->owner()->create();
        $this->assertTrue($owner->isLastActiveOwner());

        // Web UI: the sole owner may not demote or suspend themselves.
        $this->actingAs($owner);
        $this->patch(route('users.role', $owner), ['role' => 'admin'])->assertForbidden();
        $this->assertTrue($owner->refresh()->isOwner());
        $this->patch(route('users.status', $owner), ['status' => 'suspended'])->assertForbidden();
        $this->assertTrue($owner->refresh()->isActive());

        // Policy: not even another owner may remove the last active owner.
        $suspendedOwner = User::factory()->owner()->suspended()->create();
        $this->assertTrue($owner->fresh()->isLastActiveOwner());
        $this->assertFalse($suspendedOwner->can('updateRole', [$owner->fresh(), UserRole::Admin]));
        $this->assertFalse($suspendedOwner->can('updateStatus', [$owner->fresh(), UserStatus::Suspended]));
        $this->assertFalse($suspendedOwner->can('delete', $owner->fresh()));

        // Positive control: with two active owners neither is "last", so the
        // guard permits suspending, deleting, or demoting one of them.
        $secondOwner = User::factory()->owner()->create();
        $this->assertFalse($owner->fresh()->isLastActiveOwner());
        $this->assertTrue($owner->fresh()->can('updateStatus', [$secondOwner->fresh(), UserStatus::Suspended]));
        $this->assertTrue($owner->fresh()->can('delete', $secondOwner->fresh()));
        $this->actingAs($owner->fresh());
        $this->patch(route('users.role', $secondOwner), ['role' => 'admin'])
            ->assertRedirect(route('users.index'));
        $this->assertSame(UserRole::Admin, $secondOwner->refresh()->role);
    }

    // 11. Suspended users cannot log in.
    public function test_suspended_users_cannot_login(): void
    {
        $user = User::factory()->suspended()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // 12. A user suspended mid-session is blocked and logged out.
    public function test_suspended_users_are_blocked_mid_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('profile.edit'))->assertOk();

        // Suspend the account directly (status is not mass-assignable).
        $user->status = UserStatus::Suspended;
        $user->save();

        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // 13. A normal user can edit only their own profile and cannot escalate.
    public function test_users_can_only_edit_their_own_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->patch(route('profile.update'), [
            'name' => 'New Name',
            'email' => 'new-email@example.com',
        ])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new-email@example.com', $user->email);

        // Role/status must never be settable through the profile form.
        $this->patch(route('profile.update'), [
            'name' => 'New Name',
            'email' => 'new-email@example.com',
            'role' => 'owner',
            'status' => 'suspended',
        ])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->isActive());
    }

    // Bonus: the user:promote command bootstraps roles from the CLI.
    public function test_promote_command_changes_a_users_role(): void
    {
        $user = User::factory()->create(['email' => 'boss@example.com']);

        $this->artisan('user:promote', [
            'email' => 'boss@example.com',
            '--role' => 'owner',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(UserRole::Owner, $user->refresh()->role);
    }

    public function test_promote_command_fails_for_an_unknown_email(): void
    {
        $this->artisan('user:promote', [
            'email' => 'nobody@example.com',
            '--role' => 'owner',
            '--force' => true,
        ])->assertFailed();
    }

    public function test_promote_command_aborts_when_declined(): void
    {
        $user = User::factory()->create(['email' => 'keep@example.com']);

        $this->artisan('user:promote', ['email' => 'keep@example.com', '--role' => 'owner'])
            ->expectsConfirmation('Do you want to continue?', 'no')
            ->assertFailed();

        $this->assertSame(UserRole::User, $user->refresh()->role);
    }

    // Email verification must NOT be enforced on the backend (requirement 四/七).
    public function test_backend_does_not_require_email_verification(): void
    {
        $this->actingAs(User::factory()->editor()->unverified()->create());

        $this->get('/dashboard')->assertOk();
    }

    // Catalog write policies gate by role independently of the route middleware.
    public function test_catalog_policies_are_enforced_by_role(): void
    {
        $editor = User::factory()->editor()->create();
        $user = User::factory()->create();

        foreach ([Product::class, HomepageSlide::class, HomepageFeaturedPhone::class] as $model) {
            $this->assertTrue(Gate::forUser($editor)->allows('create', $model));
            $this->assertFalse(Gate::forUser($user)->allows('create', $model));
        }
    }

    private function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'brand' => 'OPPO',
            'name' => 'Featured Phone',
            'image_url' => '/dist/img/phone.png',
            'price' => '3999',
            'soc_name' => 'Test SoC',
            'battery_capacity' => 5000,
            'status' => 'published',
            'specs' => [],
        ], $attributes));
    }
}
