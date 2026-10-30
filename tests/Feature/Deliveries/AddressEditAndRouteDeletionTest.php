<?php

namespace Tests\Feature\Deliveries;

use App\Enums\DeliveryStatus;
use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeCustomer;
use App\Livewire\Deliveries\DeliveryPlanner;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AddressEditAndRouteDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_changing_the_address_clears_the_location_and_searches_again(): void
    {
        Queue::fake();
        $customer = Customer::factory()->confirmed(-24.40, -47.80)->create(['user_id' => $this->admin->id, 'street' => 'Rua Shitiro Maej', 'number' => '410']);
        Delivery::factory()->forTenant($this->admin->id)->create(['customer_id' => $customer->id]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('openAddressEditor', $customer->id)
            ->assertSet('addressForm.street', 'Rua Shitiro Maej')
            ->set('addressForm.street', 'Rua Shitiro Maeji')
            ->set('addressForm.zip_code', '11900-000')
            ->call('saveAddress')
            ->assertHasNoErrors()
            ->assertSet('editingCustomerId', null)
            ->assertDispatched('toast', fn (string $name, array $params): bool => str_contains($params['message'], 'A localização está sendo buscada de novo'));

        $customer->refresh();
        $this->assertSame('Rua Shitiro Maeji', $customer->street);
        $this->assertSame('11900000', $customer->zip_code);
        $this->assertSame(GeocodeStatus::Pending, $customer->geocode_status);
        $this->assertNull($customer->coordinates());
        $this->assertSame(Customer::hashAddress($customer->only(['street', 'number', 'district', 'city', 'state', 'zip_code'])), $customer->address_hash);
        Queue::assertPushed(GeocodeCustomer::class, fn (GeocodeCustomer $job): bool => $job->customer->is($customer));
    }

    public function test_changing_only_the_reference_keeps_the_location(): void
    {
        Queue::fake();
        $customer = Customer::factory()->confirmed(-24.49, -47.84)->create(['user_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('openAddressEditor', $customer->id)
            ->set('addressForm.reference', 'Portão azul')
            ->call('saveAddress')
            ->assertHasNoErrors();

        $customer->refresh();
        $this->assertSame('Portão azul', $customer->reference);
        $this->assertSame(['lat' => -24.49, 'lng' => -47.84], $customer->coordinates());
        Queue::assertNothingPushed();
    }

    public function test_customer_in_a_route_has_the_address_locked(): void
    {
        Queue::fake();
        $customer = Customer::factory()->confirmed(-24.49, -47.84)->create(['user_id' => $this->admin->id, 'number' => '100']);
        $route = DeliveryRoute::factory()->create(['user_id' => $this->admin->id]);
        $delivery = Delivery::factory()->forTenant($this->admin->id)->status(DeliveryStatus::Routed)->create(['customer_id' => $customer->id]);
        RouteStop::factory()->create(['delivery_route_id' => $route->id, 'delivery_id' => $delivery->id]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->assertSee('Em rota: endereço travado')
            ->assertDontSee('wire:click="openAddressEditor('.$customer->id.')"', false)
            ->call('openAddressEditor', $customer->id)
            ->assertSet('editingCustomerId', null)
            ->assertDispatched('toast', fn (string $name, array $params): bool => str_contains($params['message'], 'já está numa rota'))
            ->call('moveCustomer', $customer->id, -24.50, -47.85)
            ->call('retryGeocoding', $customer->id);

        $customer->refresh();
        $this->assertSame('100', $customer->number);
        $this->assertSame(['lat' => -24.49, 'lng' => -47.84], $customer->coordinates());
        Queue::assertNothingPushed();
    }

    public function test_marking_the_customer_on_the_map_saves_a_manual_location_without_searching(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create(['user_id' => $this->admin->id, 'geocode_status' => GeocodeStatus::Failed, 'geocode_error' => 'Endereço não encontrado.']);
        Delivery::factory()->forTenant($this->admin->id)->create(['customer_id' => $customer->id]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->assertSee('Editar endereço e marcar no mapa')
            ->call('openAddressEditor', $customer->id)
            ->assertSet('pinLatitude', '')
            ->set('pinLatitude', '-24,4961')
            ->set('pinLongitude', '-47.8471')
            ->call('saveAddress')
            ->assertHasNoErrors()
            ->assertDispatched('toast', fn (string $name, array $params): bool => str_contains($params['message'], 'O pedido já pode entrar numa rota'));

        $customer->refresh();
        $this->assertSame(GeocodeStatus::Manual, $customer->geocode_status);
        $this->assertSame(['lat' => -24.4961, 'lng' => -47.8471], $customer->coordinates());
        Queue::assertNothingPushed();
    }

    public function test_pin_coordinates_are_validated(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('openAddressEditor', $customer->id)
            ->set('pinLatitude', '95')
            ->set('pinLongitude', '')
            ->call('saveAddress')
            ->assertHasErrors(['pinLatitude', 'pinLongitude']);
    }

    public function test_address_validation(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('openAddressEditor', $customer->id)
            ->set('addressForm.street', '')
            ->set('addressForm.state', 'São')
            ->set('addressForm.zip_code', '123')
            ->call('saveAddress')
            ->assertHasErrors(['addressForm.street', 'addressForm.state', 'addressForm.zip_code']);
    }

    public function test_cannot_edit_customer_of_another_company(): void
    {
        $foreign = Customer::factory()->create(['user_id' => User::factory()->admin()->create()->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('openAddressEditor', $foreign->id);
    }

    public function test_deleting_a_planned_route_returns_orders_to_pending(): void
    {
        $route = DeliveryRoute::factory()->create(['user_id' => $this->admin->id, 'name' => 'Setor Sul']);
        $delivery = Delivery::factory()->forTenant($this->admin->id)->status(DeliveryStatus::Routed)->create(['customer_id' => Customer::factory()->confirmed()->create(['user_id' => $this->admin->id])->id]);
        RouteStop::factory()->create(['delivery_route_id' => $route->id, 'delivery_id' => $delivery->id]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('deleteRoute', $route->id)
            ->assertDispatched('toast', fn (string $name, array $params): bool => str_contains($params['message'], 'Rota "Setor Sul" excluída.'));

        $this->assertModelMissing($route);
        $this->assertSame(0, RouteStop::query()->count());
        $this->assertSame(DeliveryStatus::Pending, $delivery->refresh()->status);
    }

    public function test_route_with_finished_deliveries_cannot_be_deleted(): void
    {
        $route = DeliveryRoute::factory()->inProgress()->create(['user_id' => $this->admin->id, 'name' => 'Setor Norte']);
        $delivered = Delivery::factory()->forTenant($this->admin->id)->status(DeliveryStatus::Delivered)->create(['customer_id' => Customer::factory()->confirmed()->create(['user_id' => $this->admin->id])->id]);
        RouteStop::factory()->create(['delivery_route_id' => $route->id, 'delivery_id' => $delivered->id]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('deleteRoute', $route->id)
            ->assertDispatched('toast', fn (string $name, array $params): bool => str_contains($params['message'], 'tem entregas baixadas e não pode ser excluída'));

        $this->assertModelExists($route);
    }

    public function test_cannot_delete_route_of_another_company(): void
    {
        $route = DeliveryRoute::factory()->create(['user_id' => User::factory()->admin()->create()->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('deleteRoute', $route->id);
    }
}
