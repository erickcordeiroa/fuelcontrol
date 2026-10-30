<?php

namespace Tests\Feature\Deliveries;

use App\Enums\DeliveryRouteStatus;
use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DriverLocation;
use App\Models\User;
use App\Services\Routing\DeliveryRoutePlanner;
use App\Services\Routing\RoutePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DeliveryRoutePlannerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_creates_route_with_optimized_sequence_and_marks_deliveries_routed(): void
    {
        config(['services.routing.depot_latitude' => 0.0, 'services.routing.depot_longitude' => 0.0]);

        $far = $this->deliveryAt(0.0, 0.03);
        $near = $this->deliveryAt(0.0, 0.01);
        $middle = $this->deliveryAt(0.0, 0.02);

        $route = app(DeliveryRoutePlanner::class)->create(
            ['name' => 'Setor Sul', 'scheduled_for' => now()->toDateString()],
            collect([$far, $near, $middle]),
        );

        $this->assertSame(DeliveryRouteStatus::Planned, $route->status);
        $this->assertSame($this->admin->id, $route->user_id);
        $this->assertSame(['lat' => 0.0, 'lng' => 0.0], $route->origin());
        $this->assertSame([$near->id, $middle->id, $far->id], $route->stops()->pluck('delivery_id')->all());
        $this->assertSame([1, 2, 3], $route->stops()->pluck('sequence')->all());
        $this->assertGreaterThan(0, $route->total_distance_m);
        $this->assertNotNull($route->optimized_at);
        $this->assertSame(3, Delivery::query()->where('status', DeliveryStatus::Routed)->count());
    }

    public function test_rejects_deliveries_without_location(): void
    {
        $located = $this->deliveryAt(-16.7, -49.2);
        $withoutLocation = Delivery::factory()->forTenant($this->admin->id)->create([
            'customer_id' => Customer::factory()->create(['user_id' => $this->admin->id]),
        ]);

        $this->expectException(InvalidArgumentException::class);

        try {
            app(DeliveryRoutePlanner::class)->create(['name' => 'Rota', 'scheduled_for' => now()->toDateString()], collect([$located, $withoutLocation]));
        } finally {
            $this->assertSame(0, $located->refresh()->routeStop()->count());
        }
    }

    public function test_reoptimize_keeps_finished_stops_first(): void
    {
        $planner = app(DeliveryRoutePlanner::class);
        $a = $this->deliveryAt(0.0, 0.01);
        $b = $this->deliveryAt(0.0, 0.02);
        $c = $this->deliveryAt(0.0, 0.03);

        $route = $planner->create(['name' => 'Rota', 'scheduled_for' => now()->toDateString(), 'origin_latitude' => 0.0, 'origin_longitude' => 0.0], collect([$a, $b, $c]));
        $c->update(['status' => DeliveryStatus::Delivered]);

        $planner->reoptimize($route);

        /** O restante parte da última parada baixada (c, mais longe da origem) e volta: c → b → a. */
        $this->assertSame([$c->id, $b->id, $a->id], $route->stops()->pluck('delivery_id')->all());
    }

    public function test_reoptimize_in_progress_starts_from_the_driver_position_and_keeps_checkout_time(): void
    {
        $planner = app(DeliveryRoutePlanner::class);
        $a = $this->deliveryAt(0.0, 0.01);
        $b = $this->deliveryAt(0.0, 0.02);
        $c = $this->deliveryAt(0.0, 0.05);

        $route = $planner->create(['name' => 'Rota', 'scheduled_for' => now()->toDateString(), 'origin_latitude' => 0.0, 'origin_longitude' => 0.0], collect([$a, $b, $c]));
        $route->update(['status' => DeliveryRouteStatus::InProgress]);
        $a->update(['status' => DeliveryStatus::Delivered]);
        $arrivedAt = now()->subMinutes(5)->startOfSecond();
        $route->stops()->where('delivery_id', $a->id)->update(['arrived_at' => $arrivedAt]);
        DriverLocation::factory()->create(['user_id' => $this->admin->id, 'delivery_route_id' => $route->id, 'latitude' => 0.0, 'longitude' => 0.06, 'recorded_at' => now()]);

        $planner->reoptimize($route);

        $this->assertSame([$a->id, $c->id, $b->id], $route->stops()->pluck('delivery_id')->all());
        $this->assertEquals($arrivedAt, $route->stops()->where('delivery_id', $a->id)->sole()->arrived_at);
    }

    public function test_added_deliveries_are_fitted_into_the_open_stops_without_reordering_them(): void
    {
        $planner = app(DeliveryRoutePlanner::class);
        $a = $this->deliveryAt(0.0, 0.01);
        $c = $this->deliveryAt(0.0, 0.03);
        $d = $this->deliveryAt(0.0, 0.04);
        $route = $planner->create(['name' => 'Rota', 'scheduled_for' => now()->toDateString()], collect([$a, $c, $d]));
        $a->update(['status' => DeliveryStatus::Delivered]);

        $b = $this->deliveryAt(0.0, 0.02);
        $planner->addDeliveries($route, collect([$b]));

        $this->assertSame([$a->id, $b->id, $c->id, $d->id], $route->stops()->pluck('delivery_id')->all());
        $this->assertSame([1, 2, 3, 4], $route->stops()->pluck('sequence')->all());
        $this->assertSame(RoutePlan::ORDER_BASE, $route->refresh()->order_source);
        $this->assertSame(DeliveryStatus::Routed, $b->refresh()->status);
    }

    public function test_cannot_add_deliveries_to_a_completed_route(): void
    {
        $planner = app(DeliveryRoutePlanner::class);
        $route = $planner->create(['name' => 'Rota', 'scheduled_for' => now()->toDateString()], collect([$this->deliveryAt(0.0, 0.01)]));
        $route->update(['status' => DeliveryRouteStatus::Completed]);
        $late = $this->deliveryAt(0.0, 0.02);

        $this->expectException(InvalidArgumentException::class);

        try {
            $planner->addDeliveries($route, collect([$late]));
        } finally {
            $this->assertSame(0, $late->refresh()->routeStop()->count());
        }
    }

    public function test_route_does_not_need_reoptimization_until_something_changes(): void
    {
        config(['services.routing.provider' => 'local']);
        $planner = app(DeliveryRoutePlanner::class);
        $a = $this->deliveryAt(0.0, 0.01);
        $route = $planner->create(['name' => 'Rota', 'scheduled_for' => now()->toDateString()], collect([$a, $this->deliveryAt(0.0, 0.02)]));

        $this->assertNull($planner->reoptimizationReason($route->load('stops.delivery.customer')));

        $this->travel(1)->minute();
        $a->customer->confirmLocation(0.0, 0.015);

        $this->assertSame('Localização de cliente mudou', $planner->reoptimizationReason($route->load('stops.delivery.customer')));
    }

    public function test_straight_line_route_asks_for_reoptimization_when_road_routing_is_available(): void
    {
        config(['services.routing.provider' => 'local']);
        $planner = app(DeliveryRoutePlanner::class);
        $route = $planner->create(['name' => 'Rota', 'scheduled_for' => now()->toDateString()], collect([$this->deliveryAt(0.0, 0.01)]));

        config(['services.routing.provider' => 'geoapify', 'services.geocoding.geoapify_key' => 'test-key']);

        $this->assertSame('Traçada em linha reta', $planner->reoptimizationReason($route->load('stops.delivery.customer')));
    }

    private function deliveryAt(float $latitude, float $longitude): Delivery
    {
        return Delivery::factory()->forTenant($this->admin->id)->create([
            'customer_id' => Customer::factory()->confirmed($latitude, $longitude)->create(['user_id' => $this->admin->id]),
        ]);
    }
}
