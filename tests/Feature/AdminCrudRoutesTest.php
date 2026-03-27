<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\GasStation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_vehicle_list_create_and_edit(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        $this->get(route('vehicles.index'))->assertOk();
        $this->get(route('vehicles.create'))->assertOk();
        $this->get(route('vehicles.edit', $vehicle))->assertOk();
    }

    public function test_admin_can_open_driver_list_create_and_edit(): void
    {
        $admin = User::factory()->admin()->create();
        $driver = Driver::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        $this->get(route('drivers.index'))->assertOk();
        $this->get(route('drivers.create'))->assertOk();
        $this->get(route('drivers.edit', $driver))->assertOk();
    }

    public function test_admin_can_open_gas_station_list_create_and_edit(): void
    {
        $admin = User::factory()->admin()->create();
        $station = GasStation::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        $this->get(route('gas-stations.index'))->assertOk();
        $this->get(route('gas-stations.create'))->assertOk();
        $this->get(route('gas-stations.edit', $station))->assertOk();
    }

    public function test_legacy_ativos_paths_redirect_to_new_urls(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->get('/ativos/veiculos')->assertRedirect('/veiculos');
        $this->get('/ativos/motoristas')->assertRedirect('/motoristas');
    }
}
