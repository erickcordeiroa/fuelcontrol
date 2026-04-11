<?php

namespace Tests\Feature;

use App\Enums\FuelType;
use App\Models\GasStation;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RestorePricePerLiterStorageToMicrosMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_promotes_legacy_cent_values_without_touching_existing_micros(): void
    {
        $station = GasStation::factory()->create();
        $offering = $station->fuelOfferings()->firstOrFail();
        $trip = Trip::factory()->create();
        $fuel = $trip->fuel()->create([
            'fuel_type' => FuelType::GasolinaComum,
            'liters' => 10,
            'price_per_liter' => 6.3499,
            'station' => 'Posto teste',
        ]);

        DB::table('gas_station_fuel_offerings')->where('id', $offering->id)->update(['price_per_liter' => 590]);
        DB::table('fuels')->where('id', $fuel->id)->update(['price_per_liter' => 63_499]);

        $migration = require database_path('migrations/2026_04_11_002959_restore_price_per_liter_storage_to_micros.php');
        $migration->up();

        $this->assertSame(59_000, DB::table('gas_station_fuel_offerings')->where('id', $offering->id)->value('price_per_liter'));
        $this->assertSame(63_499, DB::table('fuels')->where('id', $fuel->id)->value('price_per_liter'));
    }
}
