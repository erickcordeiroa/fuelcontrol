<?php

namespace Tests\Feature;

use App\Livewire\Vehicles\VehicleIndex;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleIndexModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_vehicle_from_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(VehicleIndex::class)
            ->call('openCreateModal')
            ->set('plate', 'ABC-1234')
            ->set('model', 'Volvo FH')
            ->set('capacity', '25000')
            ->set('fuel_type', 'Diesel')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $admin->id,
            'plate' => 'ABC-1234',
            'model' => 'Volvo FH',
            'capacity' => 25000,
            'fuel_type' => 'Diesel',
        ]);
    }

    public function test_admin_can_update_vehicle_from_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create([
            'user_id' => $admin->id,
            'plate' => 'XYZ-9B87',
            'model' => 'Antigo',
        ]);
        $this->actingAs($admin);

        Livewire::test(VehicleIndex::class)
            ->call('openEditModal', $vehicle->id)
            ->set('model', 'Scania R450')
            ->set('capacity', '30000')
            ->call('save')
            ->assertHasNoErrors();

        $vehicle->refresh();
        $this->assertSame('Scania R450', $vehicle->model);
        $this->assertSame(30000, $vehicle->capacity);
    }
}
