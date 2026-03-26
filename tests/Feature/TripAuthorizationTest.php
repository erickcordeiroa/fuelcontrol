<?php

namespace Tests\Feature;

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
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->driverRole()->create();
        Driver::factory()->forUser($user)->create();
        $otherDriver = Driver::factory()->create();

        $trip = Trip::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $otherDriver->id,
        ]);

        $this->assertFalse($user->can('view', $trip));
    }

    public function test_admin_can_view_any_trip(): void
    {
        $trip = Trip::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('view', $trip));
    }
}
