<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Models\GasStation;
use App\Models\GasStationFuelOffering;
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
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (GasStation $station): void {
            GasStationFuelOffering::query()->firstOrCreate(
                [
                    'gas_station_id' => $station->id,
                    'fuel_type' => FuelType::GasolinaComum,
                ],
                [
                    'price_per_liter' => fake()->randomFloat(4, 4, 8),
                ]
            );
        });
    }
}
