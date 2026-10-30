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
use App\Services\Routing\RoutePlan;
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

    public function test_with_several_routes_shows_every_driver_on_one_map(): void
    {
        $first = $this->routeWithStops([['name' => 'Cliente A', 'amount' => 10.0, 'status' => DeliveryStatus::InTransit]]);
        $first->update(['status' => 'in_progress', 'started_at' => now()->subHour()]);
        $this->ping($first, now()->subMinutes(2));

        $secondDriver = Driver::factory()->create(['user_id' => $this->admin->id, 'name' => 'Ana Souza']);
        $second = $this->routeWithStops([['name' => 'Cliente B', 'amount' => 20.0, 'status' => DeliveryStatus::InTransit]], 'Setor Norte', $secondDriver);
        $second->update(['status' => 'in_progress', 'started_at' => now()->subHour()]);

        Livewire::actingAs($this->admin)
            ->test(RouteMonitor::class)
            ->assertSee('Motoristas em rota')
            ->assertSee('Carlos Mendes')
            ->assertSee('Ana Souza')
            ->assertSee('Próxima: 1. Cliente A')
            ->assertViewHas('overview', fn (array $overview): bool => $overview['inProgress'] === 2 && $overview['withoutSignal'] === 1)
            ->assertViewHas('mapPayload', fn (array $payload): bool => count($payload['drivers']) === 1
                && $payload['drivers'][0]['label'] === 'Carlos Mendes'
                && count($payload['paths']) === 2
                && $payload['paths'][0]['color'] !== $payload['paths'][1]['color'])
            ->call('selectRoute', $second->id)
            ->assertSee('Ver todos os motoristas')
            ->assertViewHas('overview', null)
            ->call('showAll')
            ->assertViewHas('route', null);
    }

    public function test_overview_marks_drivers_of_finished_routes(): void
    {
        $finished = $this->routeWithStops([['name' => 'Cliente A', 'amount' => 10.0, 'status' => DeliveryStatus::Delivered]]);
        $finished->update(['status' => 'completed', 'started_at' => now()->subHours(2), 'finished_at' => now()->subHour()]);
        $this->ping($finished, now()->subHour());

        $secondDriver = Driver::factory()->create(['user_id' => $this->admin->id, 'name' => 'Ana Souza']);
        $active = $this->routeWithStops([['name' => 'Cliente B', 'amount' => 20.0, 'status' => DeliveryStatus::InTransit]], 'Setor Norte', $secondDriver);
        $active->update(['status' => 'in_progress', 'started_at' => now()->subHour()]);
        $this->ping($active, now()->subMinute());

        Livewire::actingAs($this->admin)
            ->test(RouteMonitor::class)
            ->assertViewHas('mapPayload', fn (array $payload): bool => collect($payload['drivers'])->pluck('finished', 'label')->all() === ['Ana Souza' => false, 'Carlos Mendes' => true]);
    }

    public function test_route_map_shows_the_trail_driven_today(): void
    {
        $route = $this->routeWithStops([['name' => 'Cliente A', 'amount' => 10.0, 'status' => DeliveryStatus::InTransit]]);
        $this->ping($route, now()->subMinutes(20));
        $this->ping($route, now()->subMinutes(10));

        Livewire::actingAs($this->admin)
            ->test(RouteMonitor::class)
            ->assertViewHas('mapPayload', fn (array $payload): bool => $payload['paths'][1]['dashed'] === true && count($payload['paths'][1]['points']) === 2);
    }

    public function test_messages_stay_until_closed_and_route_notice_stays_visible(): void
    {
        $route = $this->routeWithStops([['name' => 'Cliente A', 'amount' => 10.0, 'status' => DeliveryStatus::Routed]]);
        $route->update(['routing_note' => RoutePlan::NOTE_DAILY_LIMIT]);
        session()->flash('status', 'Rota "Setor Sul" criada com 1 parada(s).');

        Livewire::actingAs($this->admin)
            ->test(RouteMonitor::class)
            ->assertSee('criada com 1 parada')
            ->call('$refresh')
            ->assertSee('criada com 1 parada')
            ->assertSee('Rota sem otimização completa.')
            ->assertSee('limite diário de créditos')
            ->call('dismissMessages')
            ->assertDontSee('criada com 1 parada');
    }

    public function test_screen_refreshes_every_ten_minutes_and_can_refresh_now(): void
    {
        $this->routeWithStops([['name' => 'Cliente A', 'amount' => 10.0, 'status' => DeliveryStatus::Routed]]);

        $this->actingAs($this->admin)
            ->get(route('deliveries.monitor'))
            ->assertOk()
            ->assertSee('wire:poll.600s.visible', false)
            ->assertSee('Atualiza a cada 10 min')
            ->assertSee('Atualizar agora');
    }

    public function test_admin_opens_monitor_page(): void
    {
        $this->routeWithStops([['name' => 'Cliente A', 'amount' => 10.0, 'status' => DeliveryStatus::Routed]]);

        $this->actingAs($this->admin)->get(route('deliveries.monitor'))->assertOk()->assertSee('Acompanhamento de rotas');
    }

    /**
     * @param  list<array{name: string, amount: float, status: DeliveryStatus}>  $stops
     */
    private function routeWithStops(array $stops, string $name = 'Setor Sul', ?Driver $driver = null): DeliveryRoute
    {
        $this->actingAs($this->admin);

        $deliveries = collect($stops)->map(fn (array $stop, int $index): Delivery => Delivery::factory()->forTenant($this->admin->id)->create([
            'customer_id' => Customer::factory()->confirmed(0.0, 0.01 * ($index + 1))->create(['user_id' => $this->admin->id, 'name' => $stop['name']]),
            'amount' => $stop['amount'],
        ]));

        $route = app(DeliveryRoutePlanner::class)->create([
            'name' => $name,
            'scheduled_for' => now()->toDateString(),
            'driver_id' => ($driver ?? $this->driver)->id,
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
            'driver_id' => $route->driver_id,
            'latitude' => 0.0,
            'longitude' => 0.025,
            'recorded_at' => $at,
        ]);
    }
}
