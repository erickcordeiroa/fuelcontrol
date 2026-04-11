<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Models\GasStation;
use App\Models\GasStationFuelOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GasStationFuelOffering>
 */
class GasStationFuelOfferingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gas_station_id' => GasStation::factory(),
            'fuel_type' => FuelType::GasolinaComum,
            'price_per_liter' => fake()->randomFloat(4, 4, 8),
        ];
    }
}
