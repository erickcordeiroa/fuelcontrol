<?php

namespace Tests\Feature;

use App\Enums\FuelType;
use App\Models\Driver;
use App\Models\GasStation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TripServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_trip_persists_fuel_and_expenses(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        $service = app(TripService::class);

        $trip = $service->createTrip($admin, [
            'date' => now()->toDateString(),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 1000,
            'km_end' => 1620,
            'revenue' => 5000,
            'liters' => 100,
            'price_per_liter' => 6,
            'fuel_type' => FuelType::GasolinaComum,
            'station' => 'Test Station',
            'toll' => 50,
            'assistant' => 30,
            'food' => 20,
        ]);

        $this->assertSame(620, $trip->km_total);
        $this->assertNotNull($trip->fuel);
        $this->assertCount(3, $trip->expenses);
    }

    public function test_create_trip_persists_gas_station_id_on_fuel(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);
        $station = GasStation::factory()->create(['user_id' => $admin->id]);
        $station->load('fuelOfferings');
        $offering = $station->fuelOfferings->first();
        $this->assertNotNull($offering);

        $this->actingAs($admin);

        $service = app(TripService::class);

        $trip = $service->createTrip($admin, [
            'date' => now()->toDateString(),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 1000,
            'km_end' => 1620,
            'revenue' => 0,
            'liters' => 100,
            'price_per_liter' => (float) $offering->price_per_liter,
            'fuel_type' => $offering->fuel_type,
            'station' => $station->name,
            'gas_station_id' => $station->id,
            'gas_station_fuel_offering_id' => $offering->id,
            'toll' => 0,
            'assistant' => 0,
            'food' => 0,
        ]);

        $this->assertSame($station->id, $trip->fuel?->gas_station_id);
        $this->assertSame($offering->id, $trip->fuel?->gas_station_fuel_offering_id);
        $this->assertTrue($offering->fuel_type === $trip->fuel?->fuel_type);
    }

    public function test_km_end_must_exceed_km_start(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        $service = app(TripService::class);

        $this->expectException(ValidationException::class);

        $service->createTrip($admin, [
            'date' => now()->toDateString(),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 500,
            'km_end' => 400,
            'revenue' => 0,
            'liters' => 0,
            'price_per_liter' => 0,
            'fuel_type' => FuelType::GasolinaComum,
            'toll' => 0,
            'assistant' => 0,
            'food' => 0,
        ]);
    }

    public function test_driver_cannot_create_trip_for_another_driver(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->driverRole()->create();
        $driverA = Driver::factory()->forLinkedUser($user)->create(['user_id' => $admin->id]);
        $driverB = Driver::factory()->create(['user_id' => $admin->id]);
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($user);

        $service = app(TripService::class);

        $this->expectException(ValidationException::class);

        $service->createTrip($user, [
            'date' => now()->toDateString(),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverB->id,
            'km_start' => 1,
            'km_end' => 100,
            'revenue' => 0,
            'liters' => 0,
            'price_per_liter' => 0,
            'fuel_type' => FuelType::GasolinaComum,
            'toll' => 0,
            'assistant' => 0,
            'food' => 0,
        ]);
    }

    public function test_preview_trip_metrics_matches_expected_formula(): void
    {
        $service = app(TripService::class);

        $preview = $service->previewTripMetrics([
            'km_start' => 0,
            'km_end' => 200,
            'liters' => 50,
            'price_per_liter' => 4,
            'revenue' => 1000,
            'toll' => 10,
            'assistant' => 5,
            'food' => 5,
        ]);

        $this->assertSame(200, $preview['km_total']);
        $this->assertSame(200.0, $preview['fuel_cost']);
        $this->assertSame(20.0, $preview['total_expenses']);
        $this->assertSame(780.0, $preview['net_margin']);
        $this->assertSame(4.0, $preview['efficiency_km_per_liter']);
        $this->assertSame(1.0, $preview['cost_per_km']);
    }
}
