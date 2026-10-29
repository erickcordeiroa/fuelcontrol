<?php

namespace Tests\Feature\Deliveries;

use App\Enums\DeliveryRouteStatus;
use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\User;
use App\Services\Routing\DeliveryRoutePlanner;
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

        $this->assertSame([$c->id, $a->id, $b->id], $route->stops()->pluck('delivery_id')->all());
    }

    private function deliveryAt(float $latitude, float $longitude): Delivery
    {
        return Delivery::factory()->forTenant($this->admin->id)->create([
            'customer_id' => Customer::factory()->confirmed($latitude, $longitude)->create(['user_id' => $this->admin->id]),
        ]);
    }
}
