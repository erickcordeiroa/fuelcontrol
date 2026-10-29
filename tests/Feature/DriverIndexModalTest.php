<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Drivers\DriverIndex;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_phone_is_stored_as_digits_and_is_unique_in_the_company(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(DriverIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Carlos Nakamura')
            ->set('license_number', '123')
            ->set('phone', '(13) 99722-1580')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('drivers', ['user_id' => $admin->id, 'phone' => '13997221580']);

        Livewire::test(DriverIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Outro')
            ->set('license_number', '456')
            ->set('phone', '13 99722 1580')
            ->call('save')
            ->assertHasErrors(['phone']);

        Driver::factory()->create(['user_id' => User::factory()->admin()->create()->id, 'phone' => '13997221580']);
        $this->assertSame(2, Driver::query()->withoutGlobalScopes()->where('phone', '13997221580')->count());
    }

    public function test_phone_without_area_code_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(DriverIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Carlos')
            ->set('license_number', '123')
            ->set('phone', '99722-1580')
            ->call('save')
            ->assertHasErrors(['phone']);
    }

    public function test_app_password_creates_the_driver_access_without_email(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(DriverIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Carlos Nakamura')
            ->set('license_number', '123')
            ->set('phone', '(13) 99722-1580')
            ->set('app_password', 'segredo1')
            ->call('save')
            ->assertHasNoErrors();

        $driver = Driver::query()->where('phone', '13997221580')->sole();
        $access = $driver->linkedUser;
        $this->assertNotNull($access);
        $this->assertNull($access->email);
        $this->assertSame(UserRole::Driver, $access->role);
        $this->assertTrue(Hash::check('segredo1', $access->password));
        $this->assertSame($admin->id, $access->tenantOwnerId());

        $this->postJson(route('api.v1.driver.login'), ['phone' => '13997221580', 'password' => 'segredo1'])->assertOk();
    }

    public function test_app_password_requires_a_phone(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(DriverIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Carlos')
            ->set('license_number', '123')
            ->set('app_password', 'segredo1')
            ->call('save')
            ->assertHasErrors(['phone']);
    }

    public function test_changing_the_password_keeps_it_when_blank_and_revokes_app_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $access = User::factory()->driverRole()->create(['email' => null]);
        $driver = Driver::factory()->forLinkedUser($access)->create(['user_id' => $admin->id, 'phone' => '13997221580']);
        $access->createToken('driver-app:test', ['driver']);
        $this->actingAs($admin);

        Livewire::test(DriverIndex::class)
            ->call('openEditModal', $driver->id)
            ->set('name', 'Carlos N.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('password', $access->refresh()->password));
        $this->assertSame('Carlos N.', $access->name);
        $this->assertSame(1, $access->tokens()->count());

        Livewire::test(DriverIndex::class)
            ->call('openEditModal', $driver->id)
            ->set('app_password', 'novasenha')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('novasenha', $access->refresh()->password));
        $this->assertSame(0, $access->tokens()->count());
    }

    public function test_deleting_the_driver_removes_its_app_access(): void
    {
        $admin = User::factory()->admin()->create();
        $access = User::factory()->driverRole()->create(['email' => null]);
        $driver = Driver::factory()->forLinkedUser($access)->create(['user_id' => $admin->id]);
        $this->actingAs($admin);

        Livewire::test(DriverIndex::class)->call('delete', $driver->id);

        $this->assertModelMissing($driver);
        $this->assertModelMissing($access);
    }
}
