<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\GasStation;
use App\Models\User;

class GasStationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GasStation $gasStation): bool
    {
        return (int) ($gasStation->user_id ?? 0) === $user->tenantOwnerId();
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, GasStation $gasStation): bool
    {
        return $user->role === UserRole::Admin
            && (int) ($gasStation->user_id ?? 0) === $user->tenantOwnerId();
    }

    public function delete(User $user, GasStation $gasStation): bool
    {
        return $user->role === UserRole::Admin
            && (int) ($gasStation->user_id ?? 0) === $user->tenantOwnerId();
    }

    public function restore(User $user, GasStation $gasStation): bool
    {
        return false;
    }

    public function forceDelete(User $user, GasStation $gasStation): bool
    {
        return false;
    }
}
