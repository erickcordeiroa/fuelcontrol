<?php

namespace Database\Factories;

use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\DriverLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverLocation>
 */
class DriverLocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'delivery_route_id' => DeliveryRoute::factory(),
            'driver_id' => Driver::factory(),
            'latitude' => fake()->latitude(-24.53, -24.48),
            'longitude' => fake()->longitude(-47.88, -47.81),
            'accuracy_m' => fake()->numberBetween(5, 30),
            'speed_kmh' => fake()->randomFloat(1, 0, 60),
            'recorded_at' => now(),
        ];
    }
}
