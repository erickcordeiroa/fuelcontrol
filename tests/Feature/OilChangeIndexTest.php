<?php

namespace Tests\Feature;

use App\Livewire\OilChanges\OilChangeIndex;
use App\Models\OilChange;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OilChangeIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_oil_change_from_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $this->actingAs($admin);

        Livewire::test(OilChangeIndex::class)
            ->call('openCreateModal')
            ->set('vehicleId', $vehicle->id)
            ->set('date', '2026-04-01')
            ->set('km_at_change', '53216')
            ->set('oil_brand', 'Mobil')
            ->set('interval_km', '10000')
            ->set('notes', 'Trocou também o filtro')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('oil_changes', [
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'km_at_change' => 53216,
            'oil_brand' => 'Mobil',
            'interval_km' => 10000,
            'notes' => 'Trocou também o filtro',
        ]);

        $this->assertSame('2026-04-01', OilChange::query()->first()->date->toDateString());
    }

    public function test_admin_can_update_oil_change_from_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $oilChange = OilChange::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'oil_brand' => 'Antiga',
            'interval_km' => 5000,
        ]);
        $this->actingAs($admin);

        Livewire::test(OilChangeIndex::class)
            ->call('openEditModal', $oilChange->id)
            ->set('oil_brand', 'Shell')
            ->set('interval_km', '10000')
            ->call('save')
            ->assertHasNoErrors();

        $oilChange->refresh();
        $this->assertSame('Shell', $oilChange->oil_brand);
        $this->assertSame(10000, $oilChange->interval_km);
    }

    public function test_admin_can_delete_oil_change(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $oilChange = OilChange::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
        ]);
        $this->actingAs($admin);

        Livewire::test(OilChangeIndex::class)
            ->call('delete', $oilChange->id);

        $this->assertDatabaseMissing('oil_changes', ['id' => $oilChange->id]);
    }

    public function test_save_validates_interval_km_is_5000_or_10000(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $this->actingAs($admin);

        Livewire::test(OilChangeIndex::class)
            ->call('openCreateModal')
            ->set('vehicleId', $vehicle->id)
            ->set('date', '2026-04-01')
            ->set('km_at_change', '53216')
            ->set('oil_brand', 'Shell')
            ->set('interval_km', '7500')
            ->call('save')
            ->assertHasErrors(['interval_km']);
    }

    public function test_save_requires_km_at_change(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $this->actingAs($admin);

        Livewire::test(OilChangeIndex::class)
            ->call('openCreateModal')
            ->set('vehicleId', $vehicle->id)
            ->set('date', '2026-04-01')
            ->set('km_at_change', '')
            ->set('oil_brand', 'Shell')
            ->set('interval_km', '10000')
            ->call('save')
            ->assertHasErrors(['km_at_change']);
    }

    public function test_driver_cannot_access_index_route(): void
    {
        $driver = User::factory()->driverRole()->create();
        $this->actingAs($driver);

        $this->get('/trocas-oleo')->assertForbidden();
    }

    public function test_older_oil_change_shows_finalizado_when_newer_exists_for_same_vehicle(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        OilChange::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-01-10',
            'interval_km' => 10000,
        ]);
        OilChange::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'date' => CarbonImmutable::now()->subDays(5)->toDateString(),
            'interval_km' => 10000,
        ]);
        $this->actingAs($admin);

        Livewire::test(OilChangeIndex::class)
            ->assertSee('Finalizado')
            ->assertSee('Em dia');
    }
}
