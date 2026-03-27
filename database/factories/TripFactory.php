<?php

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
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
            'km_start' => $kmStart,
            'km_end' => $kmEnd,
            'km_total' => $kmEnd - $kmStart,
            'revenue' => fake()->randomFloat(2, 500, 15_000),
            'status' => TripStatus::Completed,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Trip $trip): void {
            if ($trip->vehicle_id === null || $trip->driver_id === null) {
                $ownerId = User::factory()->create()->id;
                $trip->user_id = $ownerId;
                if ($trip->vehicle_id === null) {
                    $trip->vehicle_id = Vehicle::factory()->create(['user_id' => $ownerId])->id;
                }
                if ($trip->driver_id === null) {
                    $trip->driver_id = Driver::factory()->create(['user_id' => $ownerId])->id;
                }
            }

            if ($trip->user_id === null && $trip->vehicle_id !== null) {
                $uid = Vehicle::withoutGlobalScopes()->find($trip->vehicle_id)?->user_id;
                if ($uid !== null) {
                    $trip->user_id = (int) $uid;
                }
            }
        });
    }
}
