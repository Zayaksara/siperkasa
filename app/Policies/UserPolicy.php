<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\Concerns\AdminOnly;

class UserPolicy
{
    use AdminOnly;

    public function restore(User $user, mixed $model = null): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
