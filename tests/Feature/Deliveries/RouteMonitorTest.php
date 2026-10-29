<?php

namespace Tests\Feature\Deliveries;

use App\Enums\DeliveryStatus;
use App\Livewire\Deliveries\RouteMonitor;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\User;
use App\Services\Routing\DeliveryRoutePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RouteMonitorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->driver = Driver::factory()->create(['user_id' => $this->admin->id, 'name' => 'Carlos Mendes']);
    }

    public function test_shows_route_progress_amount_and_next_stop(): void
    {
        $route = $this->routeWithStops([
            ['name' => 'Mercearia São Jorge', 'amount' => 100.0, 'status' => DeliveryStatus::Delivered],
            ['name' => 'Padaria Pão Dourado', 'amount' => 50.0, 'status' => DeliveryStatus::Failed],
            ['name' => 'Açougue Boi Gordo', 'amount' => 70.0, 'status' => DeliveryStatus::InTransit],
            ['name' => 'Empório Bela Vista', 'amount' => 30.0, 'status' => DeliveryStatus::Routed],
        ]);
        $this->ping($route, now()->subMinute());

        Livewire::actingAs($this->admin)
            ->test(RouteMonitor::class)
            ->assertSee('Carlos Mendes')
            ->assertSee('Setor Sul')
            ->assertSee('2/4')
            ->assertSee('R$ 100,00')
            ->assertSee('3. Açougue Boi Gordo')
            ->assertSee('Última posição há 1 min')
            ->assertDispatched('route-map-update');
    }

    public function test_flags_driver_without_recent_position_as_idle(): void
    {
        $route = $this->routeWithStops([['name' => 'Cliente A', 'amount' => 10.0, 'status' => DeliveryStatus::InTransit]]);
        $route->update(['status' => 'in_progress', 'started_at' => now()->subHour()]);
        $this->ping($route, now()->subMinutes(14));

        Livewire::actingAs($this->admin)
            ->test(RouteMonitor::class)
            ->assertSee('Parado há 14 min');
    }

    public function test_route_of_another_tenant_is_not_shown(): void
    {
        $otherAdmin = User::factory()->admin()->create();
        $foreignRoute = DeliveryRoute::factory()->create(['user_id' => $otherAdmin->id, 'name' => 'Rota Concorrente']);

        Livewire::actingAs($this->admin)
            ->withQueryParams(['rota' => $foreignRoute->id])
            ->test(RouteMonitor::class)
            ->assertDontSee('Rota Concorrente')
            ->assertSee('Nenhuma rota nesta data.');
    }

    public function test_admin_opens_monitor_page(): void
    {
        $this->routeWithStops([['name' => 'Cliente A', 'amount' => 10.0, 'status' => DeliveryStatus::Routed]]);

        $this->actingAs($this->admin)->get(route('deliveries.monitor'))->assertOk()->assertSee('Acompanhamento de rotas');
    }

    /**
     * @param  list<array{name: string, amount: float, status: DeliveryStatus}>  $stops
     */
    private function routeWithStops(array $stops): DeliveryRoute
    {
        $this->actingAs($this->admin);

        $deliveries = collect($stops)->map(fn (array $stop, int $index): Delivery => Delivery::factory()->forTenant($this->admin->id)->create([
            'customer_id' => Customer::factory()->confirmed(0.0, 0.01 * ($index + 1))->create(['user_id' => $this->admin->id, 'name' => $stop['name']]),
            'amount' => $stop['amount'],
        ]));

        $route = app(DeliveryRoutePlanner::class)->create([
            'name' => 'Setor Sul',
            'scheduled_for' => now()->toDateString(),
            'driver_id' => $this->driver->id,
            'origin_latitude' => 0.0,
            'origin_longitude' => 0.0,
        ], $deliveries);

        $deliveries->each(fn (Delivery $delivery, int $index) => $delivery->update(['status' => $stops[$index]['status']]));

        return $route;
    }

    private function ping(DeliveryRoute $route, mixed $at): void
    {
        DriverLocation::factory()->create([
            'user_id' => $this->admin->id,
            'delivery_route_id' => $route->id,
            'driver_id' => $this->driver->id,
            'latitude' => 0.0,
            'longitude' => 0.025,
            'recorded_at' => $at,
        ]);
    }
}
