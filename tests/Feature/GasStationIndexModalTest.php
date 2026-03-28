<?php

namespace Tests\Feature;

use App\Enums\FuelType;
use App\Livewire\GasStations\GasStationIndex;
use App\Models\GasStation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class GasStationIndexModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_gas_station_from_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $rowKey = (string) Str::uuid();

        Livewire::test(GasStationIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Posto Central')
            ->set('phone', '(11) 98888-7777')
            ->set('address', 'Av. Brasil, 100')
            ->set('fuel_offerings', [
                $rowKey => [
                    'id' => null,
                    'fuel_type' => FuelType::GasolinaComum->value,
                    'price_per_liter' => '5,90',
                ],
            ])
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
        $station->load('fuelOfferings');
        $this->assertCount(1, $station->fuelOfferings);
        $this->assertEqualsWithDelta(5.9, (float) $station->fuelOfferings->first()->price_per_liter, 0.01);
    }

    public function test_admin_can_update_gas_station_from_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $station = GasStation::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Antigo',
        ]);
        $station->load('fuelOfferings');
        $offeringId = $station->fuelOfferings->first()->id;

        $this->actingAs($admin);

        $test = Livewire::test(GasStationIndex::class)->call('openEditModal', $station->id);
        $rowKey = array_key_first($test->get('fuel_offerings'));
        $this->assertNotNull($rowKey);

        $fuelOfferings = $test->get('fuel_offerings');
        $fuelOfferings[$rowKey]['price_per_liter'] = '6,00';

        $test->set('name', 'Novo Nome')
            ->set('fuel_offerings', $fuelOfferings)
            ->call('save')
            ->assertHasNoErrors();

        $station->refresh()->load('fuelOfferings');
        $this->assertSame('Novo Nome', $station->name);
        $this->assertEqualsWithDelta(6.0, (float) $station->fuelOfferings->first()->price_per_liter, 0.0001);
    }

    public function test_admin_can_delete_gas_station_after_confirming_in_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $station = GasStation::factory()->create(['user_id' => $admin->id]);
        $this->actingAs($admin);

        Livewire::test(GasStationIndex::class)
            ->call('openDeleteModal', $station->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('pendingDeleteId', $station->id)
            ->call('confirmPendingDelete')
            ->assertSet('showDeleteModal', false)
            ->assertSet('pendingDeleteId', null);

        $this->assertDatabaseMissing('gas_stations', ['id' => $station->id]);
    }
}
