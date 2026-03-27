<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_cannot_view_another_drivers_trip(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $user = User::factory()->driverRole()->create();
        Driver::factory()->forLinkedUser($user)->create(['user_id' => $admin->id]);
        $otherDriver = Driver::factory()->create(['user_id' => $admin->id]);

        $trip = Trip::query()->create([
            'user_id' => $admin->id,
            'date' => now()->toDateString(),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $otherDriver->id,
            'km_start' => 0,
            'km_end' => 100,
            'km_total' => 100,
            'revenue' => 0,
            'status' => TripStatus::Completed,
        ]);

        $this->assertFalse($user->can('view', $trip));
    }

    public function test_admin_can_view_any_trip(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $admin->id]);
        $driver = Driver::factory()->create(['user_id' => $admin->id]);

        $trip = Trip::query()->create([
            'user_id' => $admin->id,
            'date' => now()->toDateString(),
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'km_start' => 0,
            'km_end' => 100,
            'km_total' => 100,
            'revenue' => 0,
            'status' => TripStatus::Completed,
        ]);

        $this->assertTrue($admin->can('view', $trip));
    }
}
