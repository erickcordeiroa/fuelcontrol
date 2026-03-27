<?php

namespace Database\Factories;

use App\Models\GasStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GasStation>
 */
class GasStationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Posto',
            'phone' => fake()->optional()->numerify('(##) #####-####'),
            'address' => fake()->optional()->streetAddress(),
            'price_per_liter' => fake()->randomFloat(4, 4, 8),
        ];
    }
}
