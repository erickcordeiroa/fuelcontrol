<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Models\Fuel;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fuel>
 */
class FuelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'fuel_type' => FuelType::GasolinaComum,
            'liters' => fake()->randomFloat(2, 20, 400),
            'price_per_liter' => fake()->randomFloat(4, 4.5, 7.5),
            'station' => fake()->company(),
        ];
    }
}
