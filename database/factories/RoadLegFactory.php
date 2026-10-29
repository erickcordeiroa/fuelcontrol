<?php

namespace Database\Factories;

use App\Models\RoadLeg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoadLeg>
 */
class RoadLegFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leg_key' => hash('sha256', fake()->uuid()),
            'distance_m' => fake()->numberBetween(200, 5000),
            'duration_s' => fake()->numberBetween(60, 900),
            'path_polyline' => null,
            'navigation_steps' => [],
            'last_used_at' => now(),
        ];
    }
}
