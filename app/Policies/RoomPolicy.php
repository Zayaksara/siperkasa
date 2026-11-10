<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Room;
use App\Models\User;
use App\Policies\Concerns\AdminOnly;

class RoomPolicy
{
    use AdminOnly;

    /** Rooms synced from SiberMap are managed upstream, so they are read-only here. */
    public function update(User $user, mixed $model = null): bool
    {
        return $user->hasRole(UserRole::Admin) && ! $this->isSynced($model);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $user->hasRole(UserRole::Admin) && ! $this->isSynced($model);
    }

    private function isSynced(mixed $model): bool
    {
        return $model instanceof Room && $model->isSynced();
    }
}
