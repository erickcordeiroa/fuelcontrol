<?php

namespace Tests\Feature\Deliveries;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\GeocodingRequest;
use App\Models\RoadLeg;
use App\Models\RouteTemplate;
use App\Models\User;
use App\Services\Routing\DeliveryRoutePlanner;
use App\Services\Routing\RoutePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Rota que se repete toda semana: a ordem vem da rota base e os trechos já traçados são reaproveitados.
 */
class RouteBaseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var list<Customer> */
    private array $customers;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.routing.provider' => 'geoapify',
            'services.geocoding.geoapify_key' => 'platform-key',
            'services.geocoding.daily_limit' => 3000,
        ]);
        $this->admin = User::factory()->admin()->create();
        $this->customers = collect(range(1, 6))
            ->map(fn (int $index): Customer => Customer::factory()->confirmed(-24.49 - $index * 0.003, -47.84 - ($index % 2) * 0.004)->create(['user_id' => $this->admin->id]))
            ->all();
        $this->fakeGeoapify();
    }

    public function test_first_route_is_optimized_and_saved_as_the_base(): void
    {
        $route = $this->createRoute($this->week(0, [0, 1, 2, 3, 4]));

        $this->assertSame(RoutePlan::ORDER_PLANNER, $route->order_source);
        $this->assertSame(1, $this->plannerCalls());

        $template = RouteTemplate::query()->withoutGlobalScopes()->sole();
        $this->assertSame('Setor Sul', $template->name);
        $this->assertNotNull($template->optimized_at);
        $this->assertSame($this->routeCustomerIds($route), $template->customer_ids);
        $this->assertSame(6, RoadLeg::query()->count());
    }

    public function test_same_customers_next_week_reuse_order_and_legs_without_calling_the_service(): void
    {
        $first = $this->createRoute($this->week(0, [0, 1, 2, 3, 4]));
        $creditsBefore = $this->creditsUsed();

        $second = $this->createRoute($this->week(7, [4, 3, 2, 1, 0]));

        $this->assertSame(RoutePlan::ORDER_BASE, $second->order_source);
        $this->assertSame(RoutePlan::PROVIDER_GEOAPIFY, $second->routing_provider);
        $this->assertSame($this->routeCustomerIds($first), $this->routeCustomerIds($second));
        $this->assertSame($first->total_distance_m, $second->total_distance_m);
        $this->assertSame($creditsBefore, $this->creditsUsed());
        $this->assertSame(1, $this->plannerCalls());
    }

    public function test_a_new_customer_is_fitted_in_and_only_the_new_legs_are_traced(): void
    {
        $this->createRoute($this->week(0, [0, 1, 2, 3, 4]));
        $creditsBefore = $this->creditsUsed();

        $second = $this->createRoute($this->week(7, [0, 1, 2, 3, 4, 5]));

        $this->assertSame(RoutePlan::ORDER_BASE, $second->order_source);
        $this->assertSame(6, $second->stops()->count());
        $this->assertSame(1, $this->plannerCalls());
        $this->assertSame(2, $this->creditsUsed() - $creditsBefore);
        $this->assertContains($this->customers[5]->id, RouteTemplate::query()->withoutGlobalScopes()->sole()->customer_ids);
    }

    public function test_customer_missing_this_week_stays_in_the_base(): void
    {
        $this->createRoute($this->week(0, [0, 1, 2, 3, 4]));
        $baseBefore = RouteTemplate::query()->withoutGlobalScopes()->sole()->customer_ids;

        $second = $this->createRoute($this->week(7, [0, 1, 2, 3]));

        $this->assertSame(RoutePlan::ORDER_BASE, $second->order_source);
        $this->assertSame($baseBefore, RouteTemplate::query()->withoutGlobalScopes()->sole()->customer_ids);
    }

    public function test_too_many_new_customers_optimizes_from_scratch(): void
    {
        $this->createRoute($this->week(0, [0, 1, 2]));

        $second = $this->createRoute($this->week(7, [0, 1, 2, 3, 4]));

        $this->assertSame(RoutePlan::ORDER_PLANNER, $second->order_source);
        $this->assertSame(2, $this->plannerCalls());
    }

    public function test_from_scratch_ignores_the_base(): void
    {
        $this->createRoute($this->week(0, [0, 1, 2, 3, 4]));

        $second = $this->createRoute($this->week(7, [0, 1, 2, 3, 4]), fromScratch: true);

        $this->assertSame(RoutePlan::ORDER_PLANNER, $second->order_source);
        $this->assertSame(2, $this->plannerCalls());
    }

    public function test_base_preview_tells_what_will_happen(): void
    {
        $this->createRoute($this->week(0, [0, 1, 2, 3, 4]));
        $planner = app(DeliveryRoutePlanner::class);

        $this->assertSame(
            ['customers' => 5, 'known' => 5, 'new' => 1, 'reusable' => true],
            $planner->basePreview($this->admin->id, ' setor  SUL ', $this->week(7, [0, 1, 2, 3, 4, 5])),
        );
        $this->assertNull($planner->basePreview($this->admin->id, 'Setor Norte', $this->week(7, [0])));
    }

    public function test_merge_orders_keeps_items_next_to_their_neighbors(): void
    {
        $this->assertSame([1, 9, 2, 3], DeliveryRoutePlanner::mergeOrders([1, 2, 3], [1, 9, 3]));
        $this->assertSame([7, 1, 2, 3], DeliveryRoutePlanner::mergeOrders([1, 2, 3], [7, 1]));
        $this->assertSame([3, 1, 2], DeliveryRoutePlanner::mergeOrders([3, 1], [1, 2, 3]));
    }

    /**
     * @param  Collection<int, Delivery>  $deliveries
     */
    private function createRoute(Collection $deliveries, bool $fromScratch = false): DeliveryRoute
    {
        return app(DeliveryRoutePlanner::class)->create([
            'name' => 'Setor Sul',
            'scheduled_for' => $deliveries->first()->scheduled_for->toDateString(),
            'user_id' => $this->admin->id,
            'origin_latitude' => -24.4907,
            'origin_longitude' => -47.8420,
        ], $deliveries, $fromScratch)->refresh();
    }

    /**
     * Pedidos da semana para os clientes indicados (índices em $this->customers), na ordem dada.
     *
     * @param  list<int>  $customerIndexes
     * @return Collection<int, Delivery>
     */
    private function week(int $daysFromNow, array $customerIndexes): Collection
    {
        return collect($customerIndexes)->map(fn (int $index): Delivery => Delivery::factory()->forTenant($this->admin->id)->create([
            'customer_id' => $this->customers[$index]->id,
            'scheduled_for' => now()->addDays($daysFromNow)->toDateString(),
        ]));
    }

    /**
     * @return list<int>
     */
    private function routeCustomerIds(DeliveryRoute $route): array
    {
        return $route->stops()->orderBy('sequence')->with('delivery')->get()->map(fn ($stop): int => $stop->delivery->customer_id)->all();
    }

    private function creditsUsed(): int
    {
        return (int) GeocodingRequest::query()->withoutGlobalScopes()->sum('credits');
    }

    private function plannerCalls(): int
    {
        return GeocodingRequest::query()->withoutGlobalScopes()->where('lookup', GeocodingRequest::LOOKUP_ROUTE_PLANNER)->count();
    }

    /**
     * Route Planner devolve as paradas na ordem recebida; Routing devolve 1 km e 2 min por trecho.
     */
    private function fakeGeoapify(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/v1/routeplanner')) {
                return Http::response(['type' => 'FeatureCollection', 'features' => [[
                    'properties' => ['actions' => collect($request->data()['jobs'])->map(fn (array $job, int $position): array => [
                        'type' => 'job', 'job_index' => $position, 'job_id' => $job['id'], 'start_time' => ($position + 1) * 100,
                    ])->prepend(['type' => 'start', 'start_time' => 0])->all()],
                ]]]);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $legs = count(explode('|', $query['waypoints'])) - 1;

            return Http::response(['results' => [[
                'legs' => array_fill(0, $legs, ['distance' => 1000, 'time' => 120, 'steps' => []]),
                'geometry' => array_fill(0, $legs, [['lat' => -24.49, 'lon' => -47.84], ['lat' => -24.491, 'lon' => -47.841]]),
            ]]]);
        });
    }
}
