<?php

namespace App\Policies;

use App\Models\DeliveryRoute;
use App\Models\User;

/**
 * Só o admin do tenant planeja e acompanha rotas.
 */
class DeliveryRoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, DeliveryRoute $deliveryRoute): bool
    {
        return $this->ownsRoute($user, $deliveryRoute);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, DeliveryRoute $deliveryRoute): bool
    {
        return $this->ownsRoute($user, $deliveryRoute);
    }

    public function delete(User $user, DeliveryRoute $deliveryRoute): bool
    {
        return $this->ownsRoute($user, $deliveryRoute);
    }

    private function ownsRoute(User $user, DeliveryRoute $deliveryRoute): bool
    {
        return $user->isAdmin()
            && (int) ($deliveryRoute->user_id ?? 0) === $user->tenantOwnerId();
    }
}
