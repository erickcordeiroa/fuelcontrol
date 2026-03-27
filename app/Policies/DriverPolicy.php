<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\User;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Driver $driver): bool
    {
        if ((int) ($driver->user_id ?? 0) !== $user->tenantOwnerId()) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->driver !== null && $user->driver->id === $driver->id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->role === UserRole::Admin
            && (int) ($driver->user_id ?? 0) === $user->tenantOwnerId();
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $user->role === UserRole::Admin
            && (int) ($driver->user_id ?? 0) === $user->tenantOwnerId();
    }

    public function restore(User $user, Driver $driver): bool
    {
        return false;
    }

    public function forceDelete(User $user, Driver $driver): bool
    {
        return false;
    }
}
