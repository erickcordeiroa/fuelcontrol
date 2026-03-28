<?php

namespace Tests\Feature;

use App\Enums\FuelType;
use App\Livewire\Logbook\TripLogForm;
use App\Models\Driver;
use App\Models\TripChangeLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TripLogEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_edited_trip_writes_change_log_and_updates_trip(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        $trip = app(TripService::class)->createTrip($admin, [
            'date' => '2026-03-15',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 10_000,
            'km_end' => 10_500,
            'revenue' => 0,
            'liters' => 40,
            'price_per_liter' => 6.5,
            'fuel_type' => FuelType::Outro->value,
            'station' => 'Posto X',
            'gas_station_id' => null,
            'gas_station_fuel_offering_id' => null,
            'toll' => 5,
            'assistant' => 0,
            'food' => 0,
        ]);

        Livewire::test(TripLogForm::class, ['trip' => $trip])
            ->set('km_end', 10_800)
            ->call('save')
            ->assertRedirect(route('reports'));

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'km_end' => 10_800,
            'km_total' => 800,
        ]);

        $this->assertDatabaseCount('trip_change_logs', 1);

        $log = TripChangeLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(10_500, $log->before['km_end']);
        $this->assertSame(10_800, $log->after['km_end']);
    }

    public function test_saving_trip_without_field_changes_does_not_create_change_log(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        $trip = app(TripService::class)->createTrip($admin, [
            'date' => '2026-03-15',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 10_000,
            'km_end' => 10_500,
            'revenue' => 0,
            'liters' => 40,
            'price_per_liter' => 6.5,
            'fuel_type' => FuelType::Outro->value,
            'station' => 'Posto X',
            'gas_station_id' => null,
            'gas_station_fuel_offering_id' => null,
            'toll' => 0,
            'assistant' => 0,
            'food' => 0,
        ]);

        Livewire::test(TripLogForm::class, ['trip' => $trip])
            ->call('save')
            ->assertRedirect(route('reports'));

        $this->assertDatabaseCount('trip_change_logs', 0);
    }
}
