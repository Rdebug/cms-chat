<?php

namespace App\Policies;

use App\Models\Sector;
use App\Models\User;

class SectorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Sector $sector): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Sector $sector): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Sector $sector): bool
    {
        return $user->isAdmin();
    }
}
