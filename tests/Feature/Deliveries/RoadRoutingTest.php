<?php

namespace Tests\Feature\Deliveries;

use App\Enums\DeliveryRouteStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\GeocodingRequest;
use App\Models\User;
use App\Services\Geocoding\GeocoderFactory;
use App\Services\Routing\DeliveryRoutePlanner;
use App\Services\Routing\GeoapifyRouting;
use App\Services\Routing\RouteMapPresenter;
use App\Services\Routing\RoutePlan;
use App\Support\Polyline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoadRoutingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.routing.provider' => 'geoapify',
            'services.geocoding.geoapify_key' => 'platform-key',
            'services.geocoding.daily_limit' => 3000,
        ]);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_route_is_ordered_and_traced_by_the_streets(): void
    {
        $this->fakeGeoapify(plannerOrder: [2, 0, 1]);
        [$a, $b, $c] = $this->deliveries();

        $route = $this->createRoute([$a, $b, $c]);

        $stops = $route->stops()->get();
        $this->assertSame([$c->id, $a->id, $b->id], $stops->pluck('delivery_id')->all());
        $this->assertSame(RoutePlan::PROVIDER_GEOAPIFY, $route->routing_provider);
        $this->assertSame(1000, $stops[0]->distance_from_previous_m);
        $this->assertSame(120, $stops[0]->duration_from_previous_s);
        $this->assertSame('Vire à direita na Rua Shitiro Maeji.', $stops[0]->navigation_steps[0]['instruction']);
        $this->assertCount(3, Polyline::decode($stops[0]->path_polyline));
        $this->assertNotNull($route->return_path_polyline);
        $this->assertSame(4000, $route->total_distance_m);
        $this->assertSame(480, $route->total_duration_s);
        $this->assertStringContainsString('4,0 km pelas ruas · 8 min de direção', $route->distanceSummary());
    }

    public function test_credits_are_recorded_for_the_daily_cap_but_not_the_address_quota(): void
    {
        $this->fakeGeoapify(plannerOrder: [0, 1, 2]);

        $this->createRoute($this->deliveries());

        $planner = GeocodingRequest::query()->withoutGlobalScopes()->where('lookup', GeocodingRequest::LOOKUP_ROUTE_PLANNER)->sole();
        $routing = GeocodingRequest::query()->withoutGlobalScopes()->where('lookup', GeocodingRequest::LOOKUP_ROUTING)->sole();
        $this->assertSame(25, $planner->credits);
        $this->assertSame(4, $routing->credits);
        $this->assertSame(GeoapifyRouting::creditsFor(3, true), 29);

        $factory = app(GeocoderFactory::class);
        $this->assertSame(29, $factory->usedToday());
        $this->assertSame(0, $factory->usedThisMonth($this->admin->id));
    }

    public function test_falls_back_to_straight_line_when_the_service_fails(): void
    {
        Http::fake(['api.geoapify.com/*' => Http::response(['message' => 'erro'], 500)]);

        $route = $this->createRoute($this->deliveries());

        $this->assertSame(RoutePlan::PROVIDER_LOCAL, $route->routing_provider);
        $this->assertSame(RoutePlan::NOTE_SERVICE_FAILED, $route->routing_note);
        $this->assertSame(3, $route->stops()->count());
        $this->assertNull($route->total_duration_s);
        $this->assertStringContainsString('estimados em linha reta', $route->distanceSummary());
    }

    public function test_without_credits_for_the_optimizer_the_order_is_local_but_the_path_follows_the_streets(): void
    {
        config(['services.geocoding.daily_limit' => 10]);
        $this->fakeGeoapify(plannerOrder: [0, 1, 2]);

        $route = $this->createRoute($this->deliveries());

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/v1/routeplanner'));
        $this->assertSame(RoutePlan::PROVIDER_GEOAPIFY, $route->routing_provider);
        $this->assertSame(RoutePlan::ORDER_LOCAL, $route->order_source);
        $this->assertSame(RoutePlan::NOTE_ORDER_DAILY_LIMIT, $route->routing_note);
        $this->assertStringContainsString('não cobria a otimização', $route->routingNotice());
        $this->assertSame('Ordem não otimizada (limite do dia)', app(DeliveryRoutePlanner::class)->reoptimizationReason($route->load('stops.delivery.customer')));
    }

    public function test_does_not_call_the_service_when_the_day_has_no_credits_left(): void
    {
        config(['services.geocoding.daily_limit' => 2]);
        Http::fake();

        $route = $this->createRoute($this->deliveries());

        Http::assertNothingSent();
        $this->assertSame(RoutePlan::PROVIDER_LOCAL, $route->routing_provider);
        $this->assertSame(RoutePlan::NOTE_DAILY_LIMIT, $route->routing_note);
        $this->assertStringContainsString('limite diário de créditos', $route->routingNotice());
    }

    public function test_driver_app_receives_path_and_instructions(): void
    {
        $this->fakeGeoapify(plannerOrder: [0, 1, 2]);
        [$driverUser, $driver] = $this->driver();
        $route = $this->createRoute($this->deliveries(), $driver);

        Sanctum::actingAs($driverUser, ['driver']);

        $this->getJson(route('api.v1.driver.route.today'))
            ->assertOk()
            ->assertJsonPath('data.road_routing', true)
            ->assertJsonPath('data.total_duration_min', 8)
            ->assertJsonPath('data.stops.0.duration_from_previous_min', 2)
            ->assertJsonPath('data.stops.0.navigation.path', $route->stops()->first()->path_polyline)
            ->assertJsonPath('data.stops.0.navigation.steps.0.instruction', 'Vire à direita na Rua Shitiro Maeji.');
    }

    public function test_driver_gets_new_directions_from_the_current_position(): void
    {
        $this->fakeGeoapify(plannerOrder: [0, 1, 2]);
        [$driverUser, $driver] = $this->driver();
        $route = $this->createRoute($this->deliveries(), $driver);
        $route->update(['status' => DeliveryRouteStatus::InProgress]);

        Sanctum::actingAs($driverUser, ['driver']);

        $this->getJson(route('api.v1.driver.stops.directions', ['stop' => $route->stops()->first(), 'latitude' => -24.5, 'longitude' => -47.85]))
            ->assertOk()
            ->assertJsonPath('data.distance_km', 1)
            ->assertJsonPath('data.duration_min', 2)
            ->assertJsonPath('data.steps.0.instruction', 'Vire à direita na Rua Shitiro Maeji.');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'waypoints=-24.5%2C-47.85'));
    }

    public function test_directions_are_refused_when_the_day_has_no_credits_left(): void
    {
        $this->fakeGeoapify(plannerOrder: [0, 1, 2]);
        [$driverUser, $driver] = $this->driver();
        $route = $this->createRoute($this->deliveries(), $driver);
        config(['services.geocoding.daily_limit' => 20]);

        Sanctum::actingAs($driverUser, ['driver']);

        $this->getJson(route('api.v1.driver.stops.directions', ['stop' => $route->stops()->first(), 'latitude' => -24.5, 'longitude' => -47.85]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Limite diário do serviço de rotas atingido. Siga o trajeto já traçado.');
    }

    public function test_admin_map_draws_the_street_path(): void
    {
        $this->fakeGeoapify(plannerOrder: [0, 1, 2]);
        $route = $this->createRoute($this->deliveries());

        $payload = app(RouteMapPresenter::class)->forRoute($route->fresh());

        $this->assertCount(12, $payload['paths'][0]['points']);
    }

    /**
     * Route Planner devolve a ordem pedida; Routing devolve 1 km, 2 min e 3 pontos por trecho.
     *
     * @param  list<int>  $plannerOrder  Índices dos jobs na ordem de visita
     */
    private function fakeGeoapify(array $plannerOrder): void
    {
        Http::fake(function (Request $request) use ($plannerOrder) {
            if (str_contains($request->url(), '/v1/routeplanner')) {
                return Http::response(['type' => 'FeatureCollection', 'features' => [[
                    'properties' => ['actions' => collect($plannerOrder)->map(fn (int $job, int $position): array => [
                        'type' => 'job', 'job_index' => $job, 'job_id' => (string) $job, 'start_time' => ($position + 1) * 100,
                    ])->prepend(['type' => 'start', 'start_time' => 0])->all()],
                ]]]);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $legs = count(explode('|', $query['waypoints'])) - 1;

            return Http::response(['results' => [[
                'legs' => array_fill(0, $legs, ['distance' => 1000, 'time' => 120, 'steps' => [
                    ['from_index' => 0, 'to_index' => 2, 'distance' => 1000, 'time' => 120, 'instruction' => ['text' => 'Vire à direita na Rua Shitiro Maeji.', 'type' => 'Right']],
                ]]),
                'geometry' => array_fill(0, $legs, [['lat' => -24.49, 'lon' => -47.84], ['lat' => -24.491, 'lon' => -47.841], ['lat' => -24.492, 'lon' => -47.842]]),
            ]]]);
        });
    }

    /**
     * @return list<Delivery>
     */
    private function deliveries(): array
    {
        return collect([[-24.4933, -47.8469], [-24.4990, -47.8300], [-24.4850, -47.8550]])
            ->map(fn (array $point): Delivery => Delivery::factory()->forTenant($this->admin->id)->create([
                'customer_id' => Customer::factory()->confirmed($point[0], $point[1])->create(['user_id' => $this->admin->id]),
            ]))
            ->all();
    }

    /**
     * @param  list<Delivery>  $deliveries
     */
    private function createRoute(array $deliveries, ?Driver $driver = null): DeliveryRoute
    {
        return app(DeliveryRoutePlanner::class)->create([
            'name' => 'Rota Centro',
            'scheduled_for' => now()->toDateString(),
            'user_id' => $this->admin->id,
            'driver_id' => $driver?->id,
            'origin_latitude' => -24.4907,
            'origin_longitude' => -47.8420,
        ], collect($deliveries))->refresh();
    }

    /**
     * @return array{0: User, 1: Driver}
     */
    private function driver(): array
    {
        $user = User::factory()->driverRole()->create();

        return [$user, Driver::factory()->forLinkedUser($user)->create(['user_id' => $this->admin->id])];
    }
}
