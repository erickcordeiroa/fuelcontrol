<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteStop>
 */
class RouteStopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_route_id' => DeliveryRoute::factory(),
            'delivery_id' => Delivery::factory(),
            'sequence' => 1,
        ];
    }
}
