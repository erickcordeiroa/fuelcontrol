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

    /**
     * Motorista executa no app (spec 004) só a rota atribuída a ele.
     */
    public function drive(User $user, DeliveryRoute $deliveryRoute): bool
    {
        $driverId = $user->driver?->id;

        return ! $user->isAdmin()
            && $driverId !== null
            && $deliveryRoute->driver_id === $driverId
            && (int) ($deliveryRoute->user_id ?? 0) === $user->tenantOwnerId();
    }

    private function ownsRoute(User $user, DeliveryRoute $deliveryRoute): bool
    {
        return $user->isAdmin()
            && (int) ($deliveryRoute->user_id ?? 0) === $user->tenantOwnerId();
    }
}
