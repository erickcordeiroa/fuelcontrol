<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Trip $trip): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->driver !== null && $user->driver->id === $trip->driver_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Trip $trip): bool
    {
        return $this->view($user, $trip);
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $this->view($user, $trip);
    }

    public function restore(User $user, Trip $trip): bool
    {
        return false;
    }

    public function forceDelete(User $user, Trip $trip): bool
    {
        return false;
    }
}
