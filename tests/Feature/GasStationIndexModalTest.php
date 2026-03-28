<?php

namespace Tests\Feature;

use App\Livewire\GasStations\GasStationIndex;
use App\Models\GasStation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GasStationIndexModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_gas_station_from_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(GasStationIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Posto Central')
            ->set('phone', '(11) 98888-7777')
            ->set('address', 'Av. Brasil, 100')
            ->set('price_per_liter', '5,8999')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('gas_stations', [
            'user_id' => $admin->id,
            'name' => 'Posto Central',
            'address' => 'Av. Brasil, 100',
        ]);

        $station = GasStation::query()->where('name', 'Posto Central')->first();
        $this->assertNotNull($station);
        $this->assertEqualsWithDelta(5.8999, (float) $station->price_per_liter, 0.0001);
    }

    public function test_admin_can_update_gas_station_from_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $station = GasStation::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Antigo',
        ]);
        $this->actingAs($admin);

        Livewire::test(GasStationIndex::class)
            ->call('openEditModal', $station->id)
            ->set('name', 'Novo Nome')
            ->set('price_per_liter', '6,0000')
            ->call('save')
            ->assertHasNoErrors();

        $station->refresh();
        $this->assertSame('Novo Nome', $station->name);
        $this->assertEqualsWithDelta(6.0, (float) $station->price_per_liter, 0.0001);
    }
}
