<?php

namespace Tests\Feature\Api;

use App\Enums\DeliveryRouteStatus;
use App\Enums\DeliveryStatus;
use App\Enums\GeocodeStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\RouteStop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverAppApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $driverUser;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->driverUser = User::factory()->driverRole()->create();
        $this->driver = Driver::factory()->forLinkedUser($this->driverUser)->create([
            'user_id' => $this->admin->id,
            'phone' => '(13) 99722-1580',
        ]);
    }

    public function test_driver_logs_in_with_phone_and_receives_a_token(): void
    {
        $this->postJson(route('api.v1.driver.login'), ['phone' => '13 99722-1580', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('driver.id', $this->driver->id)
            ->assertJsonStructure(['token']);

        $this->assertSame(['driver'], $this->driverUser->tokens()->sole()->abilities);
    }

    public function test_login_refuses_wrong_password_and_phone_without_area_code(): void
    {
        $this->postJson(route('api.v1.driver.login'), ['phone' => '13997221580', 'password' => 'errada'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'Celular ou senha incorretos.']);

        $this->postJson(route('api.v1.driver.login'), ['phone' => '99722-1580', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_admin_cannot_use_the_driver_app(): void
    {
        Sanctum::actingAs($this->admin, ['driver']);

        $this->getJson(route('api.v1.driver.route.today'))->assertForbidden();
    }

    public function test_token_without_driver_ability_is_refused(): void
    {
        Sanctum::actingAs($this->driverUser, []);

        $this->getJson(route('api.v1.driver.route.today'))->assertForbidden();
    }

    public function test_today_without_route_says_so(): void
    {
        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->getJson(route('api.v1.driver.route.today'))
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Nenhuma rota para hoje.');
    }

    public function test_today_lists_open_stops_in_order_and_finished_ones_at_the_end(): void
    {
        $route = $this->routeWithStops(3, DeliveryRouteStatus::InProgress, DeliveryStatus::InTransit);
        $first = $route->stops[0];
        $first->delivery->update(['status' => DeliveryStatus::Delivered, 'delivered_at' => now()]);
        $first->update(['arrived_at' => now()]);

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->getJson(route('api.v1.driver.route.today'))
            ->assertOk()
            ->assertJsonPath('data.id', $route->id)
            ->assertJsonPath('data.next_stop_id', $route->stops[1]->id)
            ->assertJsonPath('data.progress.delivered', 1)
            ->assertJsonPath('data.stops.0.sequence', 2)
            ->assertJsonPath('data.stops.0.is_next', true)
            ->assertJsonPath('data.stops.1.sequence', 3)
            ->assertJsonPath('data.stops.2.sequence', 1)
            ->assertJsonPath('data.stops.2.status.label', 'Entregue');
    }

    public function test_driver_does_not_see_routes_of_other_drivers(): void
    {
        $other = Driver::factory()->create(['user_id' => $this->admin->id]);
        DeliveryRoute::factory()->create(['user_id' => $this->admin->id, 'driver_id' => $other->id]);

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->getJson(route('api.v1.driver.route.today'))->assertJsonPath('data', null);
    }

    public function test_starting_the_route_puts_stops_in_transit_and_records_position(): void
    {
        $route = $this->routeWithStops(2);

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->postJson(route('api.v1.driver.routes.start', $route), ['position' => ['latitude' => -24.49, 'longitude' => -47.84, 'accuracy_m' => 12]])
            ->assertOk()
            ->assertJsonPath('data.status.label', 'Em andamento')
            ->assertJsonPath('data.stops.0.status.label', 'Em rota');

        $this->assertNotNull($route->refresh()->started_at);
        $this->assertSame(1, DriverLocation::query()->withoutGlobalScopes()->where('delivery_route_id', $route->id)->count());
    }

    public function test_driver_cannot_start_a_route_of_another_driver(): void
    {
        $other = Driver::factory()->create(['user_id' => $this->admin->id]);
        $route = DeliveryRoute::factory()->create(['user_id' => $this->admin->id, 'driver_id' => $other->id]);

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->postJson(route('api.v1.driver.routes.start', $route))->assertForbidden();
    }

    public function test_delivering_the_next_stop_records_time_position_and_confirms_the_customer(): void
    {
        $route = $this->routeWithStops(2, DeliveryRouteStatus::InProgress, DeliveryStatus::InTransit);
        $stop = $route->stops[0];

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->postJson(route('api.v1.driver.stops.deliver', $stop), [
            'client_uuid' => (string) Str::uuid(),
            'position' => ['latitude' => -24.4907, 'longitude' => -47.8420, 'accuracy_m' => 15],
        ])
            ->assertOk()
            ->assertJsonPath('data.next_stop_id', $route->stops[1]->id)
            ->assertJsonPath('data.stops.1.status.label', 'Entregue');

        $delivery = $stop->delivery->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertSame($this->driver->id, $delivery->completed_by_driver_id);
        $this->assertSame(-24.4907, $delivery->completed_latitude);
        $this->assertNotNull($stop->refresh()->arrived_at);

        $customer = $delivery->customer->refresh();
        $this->assertSame(GeocodeStatus::Confirmed, $customer->geocode_status);
        $this->assertSame(['lat' => -24.4907, 'lng' => -47.842], $customer->coordinates());

        $this->assertTrue(AuditLog::query()->get()->contains(
            fn (AuditLog $log): bool => $log->metadata['model_id'] === $delivery->id && ($log->metadata['actor']['id'] ?? null) === $this->driverUser->id,
        ));
    }

    public function test_imprecise_position_does_not_confirm_the_customer(): void
    {
        $route = $this->routeWithStops(1, DeliveryRouteStatus::InProgress, DeliveryStatus::InTransit);

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->postJson(route('api.v1.driver.stops.deliver', $route->stops[0]), [
            'client_uuid' => (string) Str::uuid(),
            'position' => ['latitude' => -24.4907, 'longitude' => -47.8420, 'accuracy_m' => 450],
        ])->assertOk();

        $this->assertNull($route->stops[0]->delivery->customer->refresh()->confirmed_latitude);
    }

    public function test_stop_out_of_order_is_refused(): void
    {
        $route = $this->routeWithStops(3, DeliveryRouteStatus::InProgress, DeliveryStatus::InTransit);

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->postJson(route('api.v1.driver.stops.deliver', $route->stops[2]), ['client_uuid' => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Siga a ordem da rota: dê baixa na parada 1 antes desta.');

        $this->assertSame(DeliveryStatus::InTransit, $route->stops[2]->delivery->refresh()->status);
    }

    public function test_stop_cannot_be_completed_before_starting_the_route(): void
    {
        $route = $this->routeWithStops(1);

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->postJson(route('api.v1.driver.stops.deliver', $route->stops[0]), ['client_uuid' => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Inicie a rota para dar baixa nas entregas.');
    }

    public function test_resending_the_same_checkout_changes_nothing(): void
    {
        $route = $this->routeWithStops(2, DeliveryRouteStatus::InProgress, DeliveryStatus::InTransit);
        $uuid = (string) Str::uuid();

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->postJson(route('api.v1.driver.stops.deliver', $route->stops[0]), ['client_uuid' => $uuid])->assertOk();
        $this->postJson(route('api.v1.driver.stops.deliver', $route->stops[0]), ['client_uuid' => $uuid])->assertOk();

        $this->postJson(route('api.v1.driver.stops.deliver', $route->stops[0]), ['client_uuid' => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Esta parada já teve baixa.');
    }

    public function test_failed_stop_requires_a_reason_and_text_for_other(): void
    {
        $route = $this->routeWithStops(2, DeliveryRouteStatus::InProgress, DeliveryStatus::InTransit);
        $stop = $route->stops[0];

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->postJson(route('api.v1.driver.stops.fail', $stop), ['client_uuid' => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason' => 'Escolha o motivo da não entrega.']);

        $this->postJson(route('api.v1.driver.stops.fail', $stop), ['client_uuid' => (string) Str::uuid(), 'reason' => 'other'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note' => 'Descreva o motivo.']);

        $this->postJson(route('api.v1.driver.stops.fail', $stop), ['client_uuid' => (string) Str::uuid(), 'reason' => 'customer_absent'])
            ->assertOk()
            ->assertJsonPath('data.stops.1.failure_reason.label', 'Cliente ausente');

        $delivery = $stop->delivery->refresh();
        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertNull($delivery->delivered_at);
        $this->assertSame('Cliente ausente', $delivery->failure_reason);
    }

    public function test_other_reason_keeps_the_driver_text(): void
    {
        $route = $this->routeWithStops(1, DeliveryRouteStatus::InProgress, DeliveryStatus::InTransit);

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->postJson(route('api.v1.driver.stops.fail', $route->stops[0]), [
            'client_uuid' => (string) Str::uuid(),
            'reason' => 'other',
            'note' => 'Portão trancado, sem resposta',
        ])->assertOk();

        $this->assertSame('Portão trancado, sem resposta', $route->stops[0]->delivery->refresh()->failure_reason);
    }

    public function test_route_finishes_only_when_every_stop_is_completed(): void
    {
        $route = $this->routeWithStops(2, DeliveryRouteStatus::InProgress, DeliveryStatus::InTransit);

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->postJson(route('api.v1.driver.routes.finish', $route))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Falta dar baixa em 2 paradas.');

        foreach ($route->stops as $stop) {
            $this->postJson(route('api.v1.driver.stops.deliver', $stop), ['client_uuid' => (string) Str::uuid()])->assertOk();
        }

        $this->postJson(route('api.v1.driver.routes.finish', $route))
            ->assertOk()
            ->assertJsonPath('data.status.label', 'Concluída');

        $this->assertNotNull($route->refresh()->finished_at);
    }

    public function test_locations_batch_is_stored_once(): void
    {
        $route = $this->routeWithStops(1, DeliveryRouteStatus::InProgress, DeliveryStatus::InTransit);
        $positions = [
            ['latitude' => -24.49, 'longitude' => -47.84, 'accuracy_m' => 20, 'recorded_at' => now()->subMinutes(20)->toIso8601String()],
            ['latitude' => -24.50, 'longitude' => -47.85, 'speed_kmh' => 32.5, 'recorded_at' => now()->subMinutes(10)->toIso8601String()],
        ];

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->postJson(route('api.v1.driver.routes.locations', $route), ['positions' => $positions])
            ->assertCreated()
            ->assertJsonPath('stored', 2);

        $this->postJson(route('api.v1.driver.routes.locations', $route), ['positions' => $positions])
            ->assertJsonPath('stored', 0);

        $this->assertSame(2, DriverLocation::query()->withoutGlobalScopes()->where('delivery_route_id', $route->id)->count());
    }

    public function test_locations_are_refused_when_route_is_not_in_progress(): void
    {
        $route = $this->routeWithStops(1);

        Sanctum::actingAs($this->driverUser, ['driver']);

        $this->postJson(route('api.v1.driver.routes.locations', $route), [
            'positions' => [['latitude' => -24.49, 'longitude' => -47.84, 'recorded_at' => now()->toIso8601String()]],
        ])->assertUnprocessable();
    }

    public function test_logout_revokes_the_token(): void
    {
        $token = $this->driverUser->createToken('driver-app:test', ['driver'])->plainTextToken;

        $this->withToken($token)->postJson(route('api.v1.driver.logout'))->assertNoContent();

        $this->assertSame(0, $this->driverUser->tokens()->count());
    }

    private function routeWithStops(int $count, DeliveryRouteStatus $routeStatus = DeliveryRouteStatus::Planned, DeliveryStatus $deliveryStatus = DeliveryStatus::Routed): DeliveryRoute
    {
        $route = DeliveryRoute::factory()->create([
            'user_id' => $this->admin->id,
            'driver_id' => $this->driver->id,
            'status' => $routeStatus,
            'started_at' => $routeStatus === DeliveryRouteStatus::InProgress ? now()->subHour() : null,
        ]);

        foreach (range(1, $count) as $sequence) {
            $customer = Customer::factory()->located()->create(['user_id' => $this->admin->id]);
            $delivery = Delivery::factory()->forTenant($this->admin->id)->status($deliveryStatus)->create(['customer_id' => $customer->id]);
            RouteStop::factory()->create(['delivery_route_id' => $route->id, 'delivery_id' => $delivery->id, 'sequence' => $sequence]);
        }

        return $route->load('stops.delivery.customer');
    }
}
