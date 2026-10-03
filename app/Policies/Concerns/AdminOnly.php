<?php

namespace App\Policies\Concerns;

use App\Enums\UserRole;
use App\Models\User;

trait AdminOnly
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
