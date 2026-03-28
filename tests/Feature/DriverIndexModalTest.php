<?php

namespace Tests\Feature;

use App\Livewire\Drivers\DriverIndex;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DriverIndexModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_driver_from_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(DriverIndex::class)
            ->call('openCreateModal')
            ->set('name', 'João Silva')
            ->set('license_number', '12345678900')
            ->set('phone', '(11) 98888-7777')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('drivers', [
            'user_id' => $admin->id,
            'name' => 'João Silva',
            'license_number' => '12345678900',
            'linked_user_id' => null,
        ]);
    }

    public function test_admin_can_update_driver_from_modal(): void
    {
        $admin = User::factory()->admin()->create();
        $driver = Driver::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Antigo',
            'license_number' => '111',
        ]);
        $this->actingAs($admin);

        Livewire::test(DriverIndex::class)
            ->call('openEditModal', $driver->id)
            ->set('name', 'Novo Nome')
            ->set('license_number', '999')
            ->call('save')
            ->assertHasNoErrors();

        $driver->refresh();
        $this->assertSame('Novo Nome', $driver->name);
        $this->assertSame('999', $driver->license_number);
    }
}
