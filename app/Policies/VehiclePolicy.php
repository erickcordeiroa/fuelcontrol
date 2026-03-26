<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function restore(User $user, Vehicle $vehicle): bool
    {
        return false;
    }

    public function forceDelete(User $user, Vehicle $vehicle): bool
    {
        return false;
    }
}
