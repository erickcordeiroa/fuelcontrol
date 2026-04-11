<?php

namespace Tests\Feature;

use App\Enums\FuelType;
use App\Livewire\Logbook\TripLogForm;
use App\Models\Driver;
use App\Models\GasStation;
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

    public function test_editing_trip_preserves_historical_price_after_station_price_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);
        $station = GasStation::factory()->create(['user_id' => $admin->id]);
        $station->load('fuelOfferings');
        $offering = $station->fuelOfferings->first();

        $this->assertNotNull($offering);
        $this->actingAs($admin);

        $trip = app(TripService::class)->createTrip($admin, [
            'date' => '2026-03-15',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 10_000,
            'km_end' => 10_500,
            'revenue' => 0,
            'liters' => 40,
            'price_per_liter' => (float) $offering->price_per_liter,
            'fuel_type' => $offering->fuel_type,
            'station' => $station->name,
            'gas_station_id' => $station->id,
            'gas_station_fuel_offering_id' => $offering->id,
            'toll' => 0,
            'assistant' => 0,
            'food' => 0,
        ]);

        $historicalPrice = (float) $trip->fuel->price_per_liter;

        $offering->update([
            'price_per_liter' => $historicalPrice + 1.25,
        ]);

        Livewire::test(TripLogForm::class, ['trip' => $trip])
            ->set('notes', 'Atualizacao sem mexer no preco')
            ->call('save')
            ->assertRedirect(route('reports'));

        $trip->refresh();
        $trip->load('fuel');

        $this->assertSame('Atualizacao sem mexer no preco', $trip->notes);
        $this->assertEqualsWithDelta($historicalPrice, (float) $trip->fuel->price_per_liter, 0.0001);
        $this->assertNotEquals((float) $offering->fresh()->price_per_liter, (float) $trip->fuel->price_per_liter);
    }

    public function test_editing_trip_with_changed_price_opens_confirmation_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);
        $station = GasStation::factory()->create(['user_id' => $admin->id]);
        $station->load('fuelOfferings');
        $offering = $station->fuelOfferings->first();

        $this->assertNotNull($offering);
        $this->actingAs($admin);

        $trip = app(TripService::class)->createTrip($admin, [
            'date' => '2026-03-15',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 10_000,
            'km_end' => 10_500,
            'revenue' => 0,
            'liters' => 40,
            'price_per_liter' => (float) $offering->price_per_liter,
            'fuel_type' => $offering->fuel_type,
            'station' => $station->name,
            'gas_station_id' => $station->id,
            'gas_station_fuel_offering_id' => $offering->id,
            'toll' => 0,
            'assistant' => 0,
            'food' => 0,
        ]);

        Livewire::test(TripLogForm::class, ['trip' => $trip])
            ->set('price_per_liter', '8,9901')
            ->call('save')
            ->assertSet('showPriceUpdateModal', true)
            ->assertSet('priceUpdateStationName', $station->name)
            ->assertSet('priceUpdateFuelName', $offering->fuel_type->label())
            ->assertSet('priceUpdateFrom', number_format((float) $offering->price_per_liter, 4, ',', '.'))
            ->assertSet('priceUpdateTo', '8,9901');
    }

    public function test_canceling_price_update_modal_saves_trip_but_keeps_station_price(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);
        $station = GasStation::factory()->create(['user_id' => $admin->id]);
        $station->load('fuelOfferings');
        $offering = $station->fuelOfferings->first();

        $this->assertNotNull($offering);
        $this->actingAs($admin);

        $originalPrice = (float) $offering->price_per_liter;

        $trip = app(TripService::class)->createTrip($admin, [
            'date' => '2026-03-15',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 10_000,
            'km_end' => 10_500,
            'revenue' => 0,
            'liters' => 40,
            'price_per_liter' => $originalPrice,
            'fuel_type' => $offering->fuel_type,
            'station' => $station->name,
            'gas_station_id' => $station->id,
            'gas_station_fuel_offering_id' => $offering->id,
            'toll' => 0,
            'assistant' => 0,
            'food' => 0,
        ]);

        Livewire::test(TripLogForm::class, ['trip' => $trip])
            ->set('price_per_liter', '8,9901')
            ->call('save')
            ->call('cancelPriceUpdate')
            ->assertRedirect(route('reports'));

        $trip->refresh();
        $trip->load('fuel');

        $this->assertEqualsWithDelta(8.9901, (float) $trip->fuel->price_per_liter, 0.0001);
        $this->assertEqualsWithDelta($originalPrice, (float) $offering->fresh()->price_per_liter, 0.0001);
    }

    public function test_confirming_price_update_modal_updates_trip_and_station_price(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);
        $station = GasStation::factory()->create(['user_id' => $admin->id]);
        $station->load('fuelOfferings');
        $offering = $station->fuelOfferings->first();

        $this->assertNotNull($offering);
        $this->actingAs($admin);

        $trip = app(TripService::class)->createTrip($admin, [
            'date' => '2026-03-15',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 10_000,
            'km_end' => 10_500,
            'revenue' => 0,
            'liters' => 40,
            'price_per_liter' => (float) $offering->price_per_liter,
            'fuel_type' => $offering->fuel_type,
            'station' => $station->name,
            'gas_station_id' => $station->id,
            'gas_station_fuel_offering_id' => $offering->id,
            'toll' => 0,
            'assistant' => 0,
            'food' => 0,
        ]);

        Livewire::test(TripLogForm::class, ['trip' => $trip])
            ->set('price_per_liter', '8,9901')
            ->call('save')
            ->call('confirmPriceUpdate')
            ->assertRedirect(route('reports'));

        $trip->refresh();
        $trip->load('fuel');

        $this->assertEqualsWithDelta(8.9901, (float) $trip->fuel->price_per_liter, 0.0001);
        $this->assertEqualsWithDelta(8.9901, (float) $offering->fresh()->price_per_liter, 0.0001);
    }
}
