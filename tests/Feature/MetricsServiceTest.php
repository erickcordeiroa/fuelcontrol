<?php

namespace Tests\Feature;

use App\Enums\ExpenseType;
use App\Enums\FuelType;
use App\Enums\TripStatus;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\Fuel;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_aggregates_sum_fuel_and_operational_costs(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $owner->id]);
        $driver = Driver::factory()->create(['user_id' => $owner->id]);

        $trip = Trip::query()->create([
            'date' => now()->toDateString(),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 0,
            'km_end' => 100,
            'km_total' => 100,
            'revenue' => 0,
            'status' => TripStatus::Completed,
        ]);

        Fuel::query()->create([
            'trip_id' => $trip->id,
            'fuel_type' => FuelType::GasolinaComum,
            'liters' => 20,
            'price_per_liter' => 5.1234,
            'station' => null,
        ]);

        Expense::query()->create([
            'trip_id' => $trip->id,
            'type' => ExpenseType::Toll,
            'amount' => 50,
        ]);

        $service = app(MetricsService::class);
        $start = now()->subDay()->toDateString();
        $end = now()->addDay()->toDateString();

        $agg = $service->getAggregates($start, $end);

        $this->assertSame(102.47, $agg['total_fuel_cost']);
        $this->assertSame(50.0, $agg['total_other_expenses']);
        $this->assertSame(152.47, $agg['total_operational_cost']);
        $this->assertSame(100, $agg['total_km']);
        $this->assertSame(5.0, $agg['efficiency_km_per_liter']);
        $this->assertSame(1.02, $agg['cost_per_km']);
    }

    public function test_efficiency_and_cost_per_km_are_averaged_per_logbook_entry(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $owner->id]);
        $driver = Driver::factory()->create(['user_id' => $owner->id]);

        $tripA = Trip::query()->create([
            'date' => now()->toDateString(),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 0,
            'km_end' => 100,
            'km_total' => 100,
            'revenue' => 0,
            'status' => TripStatus::Completed,
        ]);
        Fuel::query()->create([
            'trip_id' => $tripA->id,
            'fuel_type' => FuelType::GasolinaComum,
            'liters' => 20,
            'price_per_liter' => 5,
            'station' => null,
        ]);

        $tripB = Trip::query()->create([
            'date' => now()->toDateString(),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 100,
            'km_end' => 200,
            'km_total' => 100,
            'revenue' => 0,
            'status' => TripStatus::Completed,
        ]);
        Fuel::query()->create([
            'trip_id' => $tripB->id,
            'fuel_type' => FuelType::GasolinaComum,
            'liters' => 50,
            'price_per_liter' => 4,
            'station' => null,
        ]);

        $service = app(MetricsService::class);
        $start = now()->subDay()->toDateString();
        $end = now()->addDay()->toDateString();

        $agg = $service->getAggregates($start, $end);

        $this->assertSame(3.5, $agg['efficiency_km_per_liter']);
        $this->assertSame(1.5, $agg['cost_per_km']);
    }
}
