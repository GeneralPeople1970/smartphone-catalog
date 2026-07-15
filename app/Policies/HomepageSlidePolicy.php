<?php

namespace App\Policies;

use App\Models\HomepageSlide;
use App\Models\User;

class HomepageSlidePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function view(User $user, HomepageSlide $homepageSlide): bool
    {
        return $user->canAccessAdmin();
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function update(User $user, HomepageSlide $homepageSlide): bool
    {
        return $user->canAccessAdmin();
    }

    public function delete(User $user, HomepageSlide $homepageSlide): bool
    {
        return $user->canAccessAdmin();
    }
}
