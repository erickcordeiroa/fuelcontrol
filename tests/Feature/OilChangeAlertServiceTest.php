<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Models\Driver;
use App\Models\OilChange;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\OilChangeAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OilChangeAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdminWithVehicle(?int $kmInterval = null, ?string $oilDate = null, int $kmDriven = 0, ?string $tripDate = null): array
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $vehicle = Vehicle::factory()->create([
            'user_id' => $admin->id,
            'plate' => 'TST-1234',
        ]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);

        $oilChange = null;
        if ($kmInterval !== null && $oilDate !== null) {
            $oilChange = OilChange::factory()->create([
                'user_id' => $admin->id,
                'vehicle_id' => $vehicle->id,
                'date' => $oilDate,
                'interval_km' => $kmInterval,
                'km_at_change' => 100_000,
            ]);
        }

        if ($kmDriven > 0 && $tripDate !== null) {
            Trip::factory()->create([
                'user_id' => $admin->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'date' => $tripDate,
                'km_start' => 100_000,
                'km_end' => 100_000 + $kmDriven,
                'km_total' => $kmDriven,
                'status' => TripStatus::Completed,
            ]);
        }

        Cache::flush();

        return ['admin' => $admin, 'vehicle' => $vehicle, 'oilChange' => $oilChange];
    }

    public function test_no_oil_change_records_returns_empty(): void
    {
        $this->actAsAdminWithVehicle();

        $alerts = app(OilChangeAlertService::class)->getAlerts();

        $this->assertTrue($alerts->isEmpty());
    }

    public function test_recent_change_with_few_km_does_not_alert(): void
    {
        $oilDate = CarbonImmutable::now()->subDays(10)->toDateString();
        $tripDate = CarbonImmutable::now()->subDays(5)->toDateString();
        $this->actAsAdminWithVehicle(10000, $oilDate, 500, $tripDate);

        $alerts = app(OilChangeAlertService::class)->getAlerts();

        $this->assertTrue($alerts->isEmpty());
    }

    public function test_alerts_when_within_1000_km_of_interval(): void
    {
        $oilDate = CarbonImmutable::now()->subDays(15)->toDateString();
        $tripDate = CarbonImmutable::now()->subDays(5)->toDateString();
        $this->actAsAdminWithVehicle(5000, $oilDate, 4200, $tripDate);

        $alerts = app(OilChangeAlertService::class)->getAlerts();

        $this->assertCount(1, $alerts);
        $alert = $alerts->first();
        $this->assertSame('TST-1234', $alert['plate']);
        $this->assertSame(800, $alert['km_remaining']);
        $this->assertContains('km', $alert['reasons']);
    }

    public function test_alerts_when_km_already_exceeded(): void
    {
        $oilDate = CarbonImmutable::now()->subDays(20)->toDateString();
        $tripDate = CarbonImmutable::now()->subDays(2)->toDateString();
        $this->actAsAdminWithVehicle(5000, $oilDate, 5500, $tripDate);

        $alerts = app(OilChangeAlertService::class)->getAlerts();

        $this->assertCount(1, $alerts);
        $alert = $alerts->first();
        $this->assertLessThan(0, $alert['km_remaining']);
        $this->assertContains('km', $alert['reasons']);
    }

    public function test_alerts_when_within_30_days_of_six_month_limit(): void
    {
        $oilDate = CarbonImmutable::now()->subDays(155)->toDateString();
        $this->actAsAdminWithVehicle(10000, $oilDate);

        $alerts = app(OilChangeAlertService::class)->getAlerts();

        $this->assertCount(1, $alerts);
        $alert = $alerts->first();
        $this->assertLessThanOrEqual(30, $alert['days_remaining']);
        $this->assertContains('time', $alert['reasons']);
    }

    public function test_second_oil_change_same_calendar_day_resets_km_for_latest_and_keeps_history_on_prior(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id, 'plate' => 'SAME-DAY']);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);

        $base = CarbonImmutable::now()->subDays(10)->startOfDay();
        $day = $base->toDateString();

        OilChange::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'date' => $day,
            'interval_km' => 5000,
            'occurred_at' => $base->setTime(9, 0, 0),
            'km_at_change' => 100_000,
        ]);

        OilChange::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'date' => $day,
            'interval_km' => 5000,
            'occurred_at' => $base->setTime(18, 0, 0),
            'km_at_change' => 104_200,
        ]);

        Trip::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'date' => $day,
            'trip_time' => '10:00',
            'km_start' => 100_000,
            'km_end' => 104_200,
            'km_total' => 4200,
            'status' => TripStatus::Completed,
        ]);

        Cache::flush();

        $ordered = OilChange::query()->where('vehicle_id', $vehicle->id)->orderBy('occurred_at')->orderBy('id')->get();
        $this->assertCount(2, $ordered);
        $this->assertNotNull($ordered->first()->occurred_at);
        $this->assertNotNull($ordered->last()->occurred_at);
        $this->assertTrue(
            $ordered->first()->occurred_at->lessThan($ordered->last()->occurred_at),
            'Cada troca precisa de occurred_at distinto para janelas no mesmo dia.',
        );

        $service = app(OilChangeAlertService::class);
        $firstRow = $service->computeForRecord($ordered->first());
        $secondRow = $service->computeForRecord($ordered->last());

        $this->assertSame(4200, $firstRow['km_driven']);
        $this->assertSame(800, $firstRow['km_remaining']);

        $this->assertSame(0, $secondRow['km_driven']);
        $this->assertSame(5000, $secondRow['km_remaining']);

        $alerts = $service->getAlerts();
        $this->assertTrue($alerts->isEmpty());
    }

    public function test_retroactive_oil_change_uses_km_at_change_baseline(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id, 'plate' => 'RETRO-1']);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);

        $oilDate = CarbonImmutable::now()->subDays(30)->toDateString();

        $oilChange = OilChange::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'date' => $oilDate,
            'interval_km' => 10_000,
            'km_at_change' => 53_216,
        ]);

        Trip::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'date' => CarbonImmutable::now()->subDays(20)->toDateString(),
            'km_start' => 53_500,
            'km_end' => 58_000,
            'km_total' => 4_500,
            'status' => TripStatus::Completed,
        ]);

        Trip::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'date' => CarbonImmutable::now()->subDays(5)->toDateString(),
            'km_start' => 58_000,
            'km_end' => 62_035,
            'km_total' => 4_035,
            'status' => TripStatus::Completed,
        ]);

        Cache::flush();

        $row = app(OilChangeAlertService::class)->computeForRecord($oilChange->refresh());

        $this->assertSame(53_216, $row['km_at_change']);
        $this->assertSame(62_035 - 53_216, $row['km_driven']);
        $this->assertSame(10_000 - (62_035 - 53_216), $row['km_remaining']);
    }

    public function test_km_driven_uses_next_oil_change_km_when_present(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id, 'plate' => 'NEXT-1']);

        $first = OilChange::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'date' => CarbonImmutable::now()->subDays(60)->toDateString(),
            'interval_km' => 10_000,
            'km_at_change' => 40_000,
        ]);

        OilChange::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'date' => CarbonImmutable::now()->subDays(10)->toDateString(),
            'interval_km' => 10_000,
            'km_at_change' => 49_500,
        ]);

        Cache::flush();

        $row = app(OilChangeAlertService::class)->computeForRecord($first->refresh());

        $this->assertSame(9_500, $row['km_driven']);
        $this->assertSame(500, $row['km_remaining']);
    }

    public function test_uses_only_latest_oil_change_per_vehicle(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id, 'plate' => 'TST-9999']);

        OilChange::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'date' => CarbonImmutable::now()->subDays(200)->toDateString(),
            'interval_km' => 5000,
        ]);

        OilChange::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'date' => CarbonImmutable::now()->subDays(5)->toDateString(),
            'interval_km' => 10000,
        ]);

        Cache::flush();

        $alerts = app(OilChangeAlertService::class)->getAlerts();

        $this->assertTrue($alerts->isEmpty(), 'Última troca recente não deveria gerar alerta');
    }
}
