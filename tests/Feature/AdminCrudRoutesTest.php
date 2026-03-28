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

    public function test_admin_can_open_vehicle_list(): void
    {
        $admin = User::factory()->admin()->create();
        Vehicle::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        $this->get(route('vehicles.index'))->assertOk();
    }

    public function test_admin_can_open_driver_list(): void
    {
        $admin = User::factory()->admin()->create();
        Driver::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        $this->get(route('drivers.index'))->assertOk();
    }

    public function test_admin_can_open_gas_station_list(): void
    {
        $admin = User::factory()->admin()->create();
        GasStation::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin);

        $this->get(route('gas-stations.index'))->assertOk();
    }

    public function test_legacy_ativos_paths_redirect_to_new_urls(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->get('/ativos/veiculos')->assertRedirect('/veiculos');
        $this->get('/ativos/motoristas')->assertRedirect('/motoristas');
    }
}
