<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plate' => strtoupper(fake()->bothify('???-#?##')),
            'model' => fake()->randomElement(['Volvo FH 540', 'Scania R450', 'Mercedes Actros']).' '.fake()->year(),
            'capacity' => fake()->numberBetween(10_000, 35_000),
            'fuel_type' => fake()->randomElement(['Diesel', 'Diesel S10']),
        ];
    }
}
