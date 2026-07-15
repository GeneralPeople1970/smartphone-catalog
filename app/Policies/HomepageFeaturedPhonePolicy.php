<?php

namespace App\Policies;

use App\Models\HomepageFeaturedPhone;
use App\Models\User;

class HomepageFeaturedPhonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function view(User $user, HomepageFeaturedPhone $featuredPhone): bool
    {
        return $user->canAccessAdmin();
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function update(User $user, HomepageFeaturedPhone $featuredPhone): bool
    {
        return $user->canAccessAdmin();
    }

    public function delete(User $user, HomepageFeaturedPhone $featuredPhone): bool
    {
        return $user->canAccessAdmin();
    }
}
