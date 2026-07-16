<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * `role` and `status` are deliberately excluded: they must never be set
     * through mass assignment (registration, profile updates, forged input).
     * They are guaranteed by database defaults and only changed through the
     * dedicated admin flows and the `user:promote` command.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Retained for schema compatibility only; email verification is
            // intentionally disabled (open registration, no MustVerifyEmail),
            // so this column never participates in authorization or routing.
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Whether the account is active (able to sign in and use the app).
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Whether the account has been suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    /**
     * Whether the user currently holds one of the given roles.
     */
    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Whether the user's role is at least as privileged as the given role.
     * Null-safe: an unset role (never expected, given the DB default) denies.
     */
    public function isAtLeast(UserRole $role): bool
    {
        return $this->role?->atLeast($role) ?? false;
    }

    /**
     * Whether the user is an owner.
     */
    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    /**
     * Whether the user may reach the admin backend (dashboard + /admin/*).
     */
    public function canAccessAdmin(): bool
    {
        return $this->isAtLeast(UserRole::Editor);
    }

    /**
     * Whether the user may view and manage other user accounts.
     */
    public function canManageUsers(): bool
    {
        return $this->isAtLeast(UserRole::Admin);
    }

    /**
     * Whether this user is the only remaining active owner. Used to protect
     * the last owner from being suspended, deleted, or demoted.
     */
    public function isLastActiveOwner(): bool
    {
        if (! $this->isOwner() || ! $this->isActive()) {
            return false;
        }

        return ! static::query()
            ->where('role', UserRole::Owner)
            ->where('status', UserStatus::Active)
            ->whereKeyNot($this->getKey())
            ->exists();
    }
}
