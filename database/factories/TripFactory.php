<?php

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kmStart = fake()->numberBetween(10_000, 500_000);
        $kmEnd = $kmStart + fake()->numberBetween(50, 800);

        return [
            'date' => fake()->dateTimeBetween('-3 months', 'now'),
            'vehicle_id' => Vehicle::factory(),
            'driver_id' => Driver::factory(),
            'km_start' => $kmStart,
            'km_end' => $kmEnd,
            'km_total' => $kmEnd - $kmStart,
            'revenue' => fake()->randomFloat(2, 500, 15_000),
            'status' => TripStatus::Completed,
        ];
    }
}
