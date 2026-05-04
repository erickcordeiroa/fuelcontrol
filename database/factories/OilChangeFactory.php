<?php

namespace Database\Factories;

use App\Models\OilChange;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OilChange>
 */
class OilChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'date' => fake()->dateTimeBetween('-5 months', 'now')->format('Y-m-d'),
            'oil_brand' => fake()->randomElement(['Mobil', 'Shell', 'Castrol', 'Petrobras', 'Ipiranga']),
            'interval_km' => fake()->randomElement([5000, 10000]),
            'notes' => null,
        ];
    }
}
