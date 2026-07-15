<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    /**
     * Admins and owners may view the user list.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->canManageUsers();
    }

    /**
     * Change a target user's role to $newRole.
     */
    public function updateRole(User $actor, User $target, UserRole $newRole): bool
    {
        if (! $actor->canManageUsers()) {
            return false;
        }

        // Never change your own role through the web UI.
        if ($actor->is($target)) {
            return false;
        }

        if ($actor->isOwner()) {
            // Owners may set any role, but must not demote the last active owner.
            if ($target->isOwner() && $newRole !== UserRole::Owner && $target->isLastActiveOwner()) {
                return false;
            }

            return true;
        }

        // Admins may only move accounts between `user` and `editor`; they may
        // never touch existing admins/owners nor grant admin/owner.
        return $this->isManageableByAdmin($target)
            && in_array($newRole, [UserRole::User, UserRole::Editor], true);
    }

    /**
     * Change a target user's account status to $newStatus.
     */
    public function updateStatus(User $actor, User $target, UserStatus $newStatus): bool
    {
        if (! $actor->canManageUsers()) {
            return false;
        }

        // Never suspend yourself through the web UI.
        if ($actor->is($target)) {
            return false;
        }

        if ($actor->isOwner()) {
            // Owners may manage anyone, but must not suspend the last active owner.
            if ($newStatus === UserStatus::Suspended && $target->isLastActiveOwner()) {
                return false;
            }

            return true;
        }

        // Admins may only suspend/restore plain users and editors.
        return $this->isManageableByAdmin($target);
    }

    /**
     * Delete a target user. Only owners may delete, never themselves, and
     * never the last active owner.
     */
    public function delete(User $actor, User $target): bool
    {
        if (! $actor->isOwner()) {
            return false;
        }

        if ($actor->is($target)) {
            return false;
        }

        return ! $target->isLastActiveOwner();
    }

    /**
     * Whether a non-owner admin may act on the target at all: admins may only
     * manage plain users and editors.
     */
    private function isManageableByAdmin(User $target): bool
    {
        return $target->hasRole(UserRole::User, UserRole::Editor);
    }
}
