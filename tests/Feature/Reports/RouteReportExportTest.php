<?php

namespace Tests\Feature\Reports;

use App\Enums\FuelType;
use App\Enums\TripStatus;
use App\Livewire\Reports\RouteReports;
use App\Models\Driver;
use App\Models\Fuel;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class RouteReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Vehicle $vehicleA;

    private Vehicle $vehicleB;

    private Vehicle $vehicleC;

    private Driver $driverA;

    private Driver $driverB;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->admin = User::factory()->admin()->create();
        $this->vehicleA = Vehicle::factory()->create(['user_id' => $this->admin->id, 'plate' => 'TJF3B40']);
        $this->vehicleB = Vehicle::factory()->create(['user_id' => $this->admin->id, 'plate' => 'TJQ9E29']);
        $this->vehicleC = Vehicle::factory()->create(['user_id' => $this->admin->id, 'plate' => 'TMH9H98']);
        $this->driverA = Driver::factory()->create(['user_id' => $this->admin->id, 'name' => 'Claudinei']);
        $this->driverB = Driver::factory()->create(['user_id' => $this->admin->id, 'name' => 'Fuba']);

        $this->createTrip($this->vehicleA, $this->driverA, 600, 80, 4.5);
        $this->createTrip($this->vehicleA, $this->driverA, 694, 92.88, 4.25);
        $this->createTrip($this->vehicleB, $this->driverB, 2743, 249.91, 4.56);
        $this->createTrip($this->vehicleC, $this->driverB, 1318, 168.92, 4.45);
        $this->createTrip($this->vehicleC, $this->driverB, 500, 50, 5.0, now()->subMonths(2)->toDateString());
    }

    public function test_service_returns_one_row_per_selected_vehicle(): void
    {
        $this->actingAs($this->admin);

        $rows = app(MetricsService::class)->getVehicleFuelReportRows(
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
            [$this->vehicleA->id, $this->vehicleC->id],
        );

        $this->assertCount(2, $rows);
        $this->assertSame(['TJF3B40', 'TMH9H98'], array_column($rows, 'plate'));

        $this->assertSame(['Claudinei'], $rows[0]['drivers']);
        $this->assertSame(1294, $rows[0]['km']);
        $this->assertSame(172.88, $rows[0]['liters']);
        $this->assertSame(754.74, $rows[0]['fuel_cost']);
        $this->assertSame(7.48, $rows[0]['km_per_liter']);
        $this->assertSame(0.58, $rows[0]['cost_per_km']);

        $this->assertSame(1318, $rows[1]['km']);
        $this->assertSame(168.92, $rows[1]['liters']);
    }

    public function test_service_lists_all_vehicles_with_trips_when_no_vehicle_is_selected(): void
    {
        $this->actingAs($this->admin);

        $rows = app(MetricsService::class)->getVehicleFuelReportRows(
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        );

        $this->assertSame(['TJF3B40', 'TJQ9E29', 'TMH9H98'], array_column($rows, 'plate'));
    }

    public function test_selected_vehicle_without_trips_is_listed_with_zeroed_values(): void
    {
        $this->actingAs($this->admin);
        $idleVehicle = Vehicle::factory()->create(['user_id' => $this->admin->id, 'plate' => 'AAA0A00']);

        $rows = app(MetricsService::class)->getVehicleFuelReportRows(
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
            [$idleVehicle->id],
        );

        $this->assertCount(1, $rows);
        $this->assertSame([], $rows[0]['drivers']);
        $this->assertSame(0, $rows[0]['km']);
        $this->assertSame(0.0, $rows[0]['fuel_cost']);
        $this->assertNull($rows[0]['km_per_liter']);
        $this->assertNull($rows[0]['cost_per_km']);
    }

    public function test_aggregates_accept_multiple_vehicles(): void
    {
        $this->actingAs($this->admin);

        $aggregates = app(MetricsService::class)->getAggregates(
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
            [$this->vehicleA->id, $this->vehicleB->id],
        );

        $this->assertSame(1294 + 2743, $aggregates['total_km']);
    }

    public function test_pdf_export_prints_only_selected_vehicles(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.export', [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'vehicle_ids' => [$this->vehicleA->id, $this->vehicleB->id],
            'format' => 'pdf',
        ]));

        $response->assertOk();
        $response->assertSeeInOrder(['Vendedor', 'Veículo', 'Custo combustível', 'Litros', 'Consumo médio', 'Preço médio', 'KM rodado']);
        $response->assertSeeInOrder(['Claudinei', 'TJF3B40', 'R$ 754,74', '172,88 L', '7,48 KM', 'R$ 0,58', '1.294 KM']);
        $response->assertSee('TJQ9E29');
        $response->assertDontSee('TMH9H98');
        $response->assertDontSee('Total operacional');
    }

    public function test_csv_export_downloads_one_line_per_vehicle(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.export', [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'vehicle_ids' => [$this->vehicleA->id, $this->vehicleC->id],
            'format' => 'csv',
        ]));

        $response->assertOk();
        $response->assertDownload();

        $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));

        $this->assertCount(3, $lines);
        $this->assertStringContainsString('Vendedor;Veículo;"Custo combustível"', $lines[0]);
        $this->assertStringContainsString('Claudinei;TJF3B40;"R$ 754,74";"172,88 L";"7,48 km";"R$ 0,58";"1.294 km"', $lines[1]);
        $this->assertStringContainsString('TMH9H98', $lines[2]);
    }

    public function test_driver_export_only_contains_own_trips_and_ignores_vehicle_filter(): void
    {
        $driverUser = User::factory()->driverRole()->create();
        $this->driverA->update(['linked_user_id' => $driverUser->id]);

        $response = $this->actingAs($driverUser)->get(route('reports.export', [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'vehicle_ids' => [$this->vehicleB->id],
        ]));

        $response->assertOk();
        $response->assertSee('TJF3B40');
        $response->assertDontSee('TJQ9E29');
        $response->assertDontSee('TMH9H98');
    }

    public function test_driver_without_linked_record_gets_empty_export(): void
    {
        $driverUser = User::factory()->driverRole()->create();

        $response = $this->actingAs($driverUser)->get(route('reports.export', [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Nenhum lançamento no período.');
        $response->assertDontSee('TJF3B40');
    }

    public function test_export_validates_dates(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.export', [
                'start_date' => '2026-10-31',
                'end_date' => '2026-10-01',
            ]))
            ->assertSessionHasErrors('end_date');

        $this->actingAs($this->admin)
            ->get(route('reports.export'))
            ->assertSessionHasErrors(['start_date', 'end_date']);
    }

    public function test_guest_cannot_export(): void
    {
        $this->get(route('reports.export', [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]))->assertRedirect(route('login'));
    }

    public function test_report_page_filters_multiple_vehicles_and_builds_export_links(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(RouteReports::class)
            ->set('filterVehicleIds', [(string) $this->vehicleA->id, (string) $this->vehicleC->id]);

        $component->assertViewHas('vehicleReportRows', fn (array $rows) => array_column($rows, 'plate') === ['TJF3B40', 'TMH9H98']);
        $component->assertViewHas('exportPdfUrl', fn (string $url) => str_contains(urldecode($url), 'vehicle_ids[0]='.$this->vehicleA->id)
            && str_contains(urldecode($url), 'vehicle_ids[1]='.$this->vehicleC->id)
            && str_contains($url, 'format=pdf'));

        $component->call('clearVehicleFilter')
            ->assertSet('filterVehicleIds', [])
            ->assertViewHas('vehicleReportRows', fn (array $rows) => count($rows) === 3);
    }

    private function createTrip(Vehicle $vehicle, Driver $driver, int $km, float $liters, float $pricePerLiter, ?string $date = null): Trip
    {
        $trip = Trip::query()->create([
            'user_id' => $this->admin->id,
            'date' => $date ?? now()->startOfMonth()->addDays(2)->toDateString(),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 0,
            'km_end' => $km,
            'km_total' => $km,
            'revenue' => 0,
            'status' => TripStatus::Completed,
        ]);

        Fuel::query()->create([
            'user_id' => $this->admin->id,
            'trip_id' => $trip->id,
            'fuel_type' => FuelType::GasolinaComum,
            'liters' => $liters,
            'price_per_liter' => $pricePerLiter,
            'station' => null,
        ]);

        return $trip;
    }
}
