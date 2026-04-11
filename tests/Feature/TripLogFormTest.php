<?php

namespace Tests\Feature;

use App\Livewire\Logbook\TripLogForm;
use App\Models\Driver;
use App\Models\GasStation;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TripLogFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_vehicle_prefills_km_start_with_last_trip_km_end(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);

        Trip::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'date' => '2026-01-10',
            'km_start' => 10_000,
            'km_end' => 10_500,
            'km_total' => 500,
        ]);

        Trip::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'date' => '2026-01-20',
            'km_start' => 10_500,
            'km_end' => 11_200,
            'km_total' => 700,
        ]);

        $this->actingAs($admin);

        Livewire::test(TripLogForm::class)
            ->set('vehicle_id', $vehicle->id)
            ->assertSet('km_start', 11_200);
    }

    public function test_when_same_date_last_trip_by_id_is_used_for_km_start(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);

        Trip::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'date' => '2026-02-01',
            'km_start' => 5000,
            'km_end' => 5100,
            'km_total' => 100,
        ]);

        Trip::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'date' => '2026-02-01',
            'km_start' => 5100,
            'km_end' => 5200,
            'km_total' => 100,
        ]);

        $this->actingAs($admin);

        Livewire::test(TripLogForm::class)
            ->set('vehicle_id', $vehicle->id)
            ->assertSet('km_start', 5200);
    }

    public function test_selecting_vehicle_without_prior_trips_leaves_km_start_null(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        Livewire::test(TripLogForm::class)
            ->set('vehicle_id', $vehicle->id)
            ->assertSet('km_start', null);
    }

    public function test_clearing_vehicle_clears_km_start(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);

        Trip::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 1000,
            'km_end' => 1500,
            'km_total' => 500,
        ]);

        $this->actingAs($admin);

        Livewire::test(TripLogForm::class)
            ->set('vehicle_id', $vehicle->id)
            ->assertSet('km_start', 1500)
            ->set('vehicle_id', null)
            ->assertSet('km_start', null);
    }

    public function test_trips_from_other_tenant_do_not_affect_km_start(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $otherVehicle = Vehicle::factory()->create(['user_id' => $other->id]);
        $otherDriver = Driver::factory()->create(['user_id' => $other->id]);

        Trip::factory()->create([
            'user_id' => $other->id,
            'vehicle_id' => $otherVehicle->id,
            'driver_id' => $otherDriver->id,
            'km_start' => 1,
            'km_end' => 99_999,
            'km_total' => 99_998,
        ]);

        $this->actingAs($admin);

        Livewire::test(TripLogForm::class)
            ->set('vehicle_id', $vehicle->id)
            ->assertSet('km_start', null);
    }

    public function test_selecting_gas_station_offering_loads_current_price_into_form(): void
    {
        $admin = User::factory()->admin()->create();
        $station = GasStation::factory()->create(['user_id' => $admin->id]);
        $station->load('fuelOfferings');
        $offering = $station->fuelOfferings->first();

        $this->assertNotNull($offering);
        $this->actingAs($admin);

        Livewire::test(TripLogForm::class)
            ->set('gas_station_id', $station->id)
            ->set('gas_station_fuel_offering_id', $offering->id)
            ->assertSet('price_per_liter', number_format((float) $offering->price_per_liter, 4, ',', '.'));
    }
}
