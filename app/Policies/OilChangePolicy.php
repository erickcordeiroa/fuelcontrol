<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OilChange;
use App\Models\User;

class OilChangePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OilChange $oilChange): bool
    {
        return (int) ($oilChange->user_id ?? 0) === $user->tenantOwnerId();
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, OilChange $oilChange): bool
    {
        return $user->role === UserRole::Admin
            && (int) ($oilChange->user_id ?? 0) === $user->tenantOwnerId();
    }

    public function delete(User $user, OilChange $oilChange): bool
    {
        return $user->role === UserRole::Admin
            && (int) ($oilChange->user_id ?? 0) === $user->tenantOwnerId();
    }

    public function restore(User $user, OilChange $oilChange): bool
    {
        return false;
    }

    public function forceDelete(User $user, OilChange $oilChange): bool
    {
        return false;
    }
}
