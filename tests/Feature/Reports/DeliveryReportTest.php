<?php

namespace Tests\Feature\Reports;

use App\Enums\DeliveryFailureReason;
use App\Enums\DeliveryRouteStatus;
use App\Enums\DeliveryStatus;
use App\Enums\ExpenseType;
use App\Livewire\Reports\DeliveryReports;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\Fuel;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Billing\PlanLimits;
use App\Services\Deliveries\DeliveryReport;
use App\Services\Routing\DeliveryRoutePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->driver = Driver::factory()->create(['user_id' => $this->admin->id, 'name' => 'Carlos Mendes']);
        $this->actingAs($this->admin);
    }

    public function test_summarizes_deliveries_by_driver_day_and_failure_reason(): void
    {
        $route = $this->route('2026-10-10', [
            [DeliveryStatus::Delivered, 100.0, null],
            [DeliveryStatus::Delivered, 50.0, null],
            [DeliveryStatus::Failed, 70.0, DeliveryFailureReason::CustomerAbsent],
            [DeliveryStatus::Routed, 30.0, null],
        ]);
        $route->update(['status' => DeliveryRouteStatus::Completed, 'started_at' => '2026-10-10 08:00', 'finished_at' => '2026-10-10 12:30']);

        $other = Driver::factory()->create(['user_id' => $this->admin->id, 'name' => 'Ana Souza']);
        $this->route('2026-10-11', [[DeliveryStatus::Failed, 20.0, DeliveryFailureReason::CustomerAbsent]], $other);
        $this->route('2026-11-01', [[DeliveryStatus::Delivered, 999.0, null]]);
        Delivery::factory()->forTenant($this->admin->id)->create(['scheduled_for' => '2026-10-12']);

        $report = app(DeliveryReport::class)->build('2026-10-01', '2026-10-31');

        $this->assertSame(2, $report['summary']['routes']);
        $this->assertSame(5, $report['summary']['stops']);
        $this->assertSame(2, $report['summary']['delivered']);
        $this->assertSame(2, $report['summary']['failed']);
        $this->assertSame(1, $report['summary']['open']);
        $this->assertSame(150.0, $report['summary']['amountDelivered']);
        $this->assertSame(50.0, $report['summary']['successRate']);
        $this->assertSame(1, $report['unrouted']);

        $carlos = collect($report['drivers'])->firstWhere('driver', 'Carlos Mendes');
        $this->assertSame(2, $carlos['delivered']);
        $this->assertSame(66.7, $carlos['successRate']);
        $this->assertSame(270, $carlos['avgRouteMinutes']);

        $this->assertSame(['2026-10-10', '2026-10-11'], array_column($report['days'], 'date'));
        $this->assertSame([['label' => 'Cliente ausente', 'count' => 2, 'percent' => 100.0]], $report['reasons']);
    }

    public function test_trip_costs_of_the_same_driver_and_day_give_the_cost_per_delivery(): void
    {
        $this->route('2026-10-10', [
            [DeliveryStatus::Delivered, 300.0, null],
            [DeliveryStatus::Delivered, 100.0, null],
            [DeliveryStatus::Failed, 50.0, DeliveryFailureReason::Refused],
        ]);
        $vehicle = Vehicle::factory()->create(['user_id' => $this->admin->id]);
        $trip = Trip::factory()->create(['user_id' => $this->admin->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $this->driver->id, 'date' => '2026-10-10', 'km_start' => 1000, 'km_end' => 1080, 'km_total' => 80]);
        Fuel::factory()->create(['trip_id' => $trip->id, 'user_id' => $this->admin->id, 'liters' => 10, 'price_per_liter' => 6.0]);
        Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $this->admin->id, 'type' => ExpenseType::Food, 'amount' => 40.0]);

        $report = app(DeliveryReport::class)->build('2026-10-01', '2026-10-31');

        $this->assertSame(100.0, $report['summary']['cost']);
        $this->assertSame(50.0, $report['summary']['costPerDelivery']);
        $this->assertSame(25.0, $report['summary']['costShare']);
        $this->assertSame(80, $report['drivers'][0]['realKm']);
        $this->assertSame(50.0, $report['days'][0]['costPerDelivery']);
    }

    public function test_driver_filter_limits_the_report(): void
    {
        $this->route('2026-10-10', [[DeliveryStatus::Delivered, 10.0, null]]);
        $other = Driver::factory()->create(['user_id' => $this->admin->id]);
        $this->route('2026-10-10', [[DeliveryStatus::Delivered, 20.0, null]], $other);

        $report = app(DeliveryReport::class)->build('2026-10-01', '2026-10-31', $other->id);

        $this->assertSame(1, $report['summary']['routes']);
        $this->assertSame(20.0, $report['summary']['amountDelivered']);
    }

    public function test_report_page_shows_drivers_and_is_admin_only(): void
    {
        $this->route(now()->toDateString(), [[DeliveryStatus::Failed, 10.0, DeliveryFailureReason::Closed]]);

        Livewire::test(DeliveryReports::class)
            ->assertSee('Entregas e custo por entrega')
            ->assertSee('Combustível e gastos')
            ->assertSee('Carlos Mendes')
            ->assertSee('Estabelecimento fechado');

        $this->get(route('reports.deliveries'))->assertOk();

        $driverUser = User::factory()->create();
        $this->actingAs($driverUser)->get(route('reports.deliveries'))->assertForbidden();
    }

    public function test_csv_export_follows_the_plan(): void
    {
        $this->route('2026-10-10', [[DeliveryStatus::Delivered, 10.0, null]]);
        $query = ['start_date' => '2026-10-01', 'end_date' => '2026-10-31'];

        app(PlanLimits::class)->changePlan($this->admin->id, collect(config('plans.plans'))->search(fn (array $plan): bool => $plan['features']['report_export'] === false));
        $this->get(route('reports.deliveries.export', $query))->assertForbidden();

        app(PlanLimits::class)->changePlan($this->admin->id, collect(config('plans.plans'))->search(fn (array $plan): bool => $plan['features']['report_export'] === true));
        $response = $this->get(route('reports.deliveries.export', $query))->assertOk();

        $this->assertStringContainsString('"Carlos Mendes";1;1;1;0;0;100,0%;"R$ 10,00"', $response->streamedContent());
    }

    /**
     * @param  list<array{0: DeliveryStatus, 1: float, 2: DeliveryFailureReason|null}>  $stops
     */
    private function route(string $date, array $stops, ?Driver $driver = null): DeliveryRoute
    {
        $deliveries = collect($stops)->map(fn (array $stop, int $index): Delivery => Delivery::factory()->forTenant($this->admin->id)->create([
            'customer_id' => Customer::factory()->confirmed(0.0, 0.01 * ($index + 1))->create(['user_id' => $this->admin->id]),
            'scheduled_for' => $date,
            'amount' => $stop[1],
        ]));

        $route = app(DeliveryRoutePlanner::class)->create([
            'name' => 'Rota '.$date,
            'scheduled_for' => $date,
            'driver_id' => ($driver ?? $this->driver)->id,
        ], $deliveries);

        $deliveries->each(fn (Delivery $delivery, int $index) => $delivery->update([
            'status' => $stops[$index][0],
            'failure_reason_code' => $stops[$index][2],
        ]));

        return $route;
    }
}
