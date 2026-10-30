<?php

namespace Database\Factories;

use App\Enums\DeliveryRouteStatus;
use App\Models\DeliveryRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRoute>
 */
class DeliveryRouteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'vehicle_id' => null,
            'driver_id' => null,
            'name' => 'Setor '.fake()->randomElement(['Sul', 'Norte', 'Leste']),
            'scheduled_for' => now()->toDateString(),
            'status' => DeliveryRouteStatus::Planned,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeliveryRouteStatus::InProgress,
            'started_at' => now()->subHours(2),
        ]);
    }
}
