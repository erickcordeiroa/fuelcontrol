<?php

namespace Tests\Feature\Deliveries;

use App\Enums\GeocodeStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeExpiredGeocodeCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_clears_google_coordinates_older_than_30_days_and_keeps_place_id(): void
    {
        $admin = User::factory()->admin()->create();
        $expired = Customer::factory()->located()->create([
            'user_id' => $admin->id,
            'geocoded_at' => now()->subDays(31),
            'google_place_id' => 'ChIJkeep',
        ]);

        $this->artisan('geocoding:purge-expired')->assertSuccessful();

        $expired->refresh();
        $this->assertNull($expired->latitude);
        $this->assertNull($expired->longitude);
        $this->assertNull($expired->geocoded_at);
        $this->assertSame('ChIJkeep', $expired->google_place_id);
        $this->assertSame(GeocodeStatus::Pending, $expired->geocode_status);
        $this->assertTrue($expired->needsGeocoding());
    }

    public function test_keeps_recent_cache(): void
    {
        $admin = User::factory()->admin()->create();
        $recent = Customer::factory()->located()->create([
            'user_id' => $admin->id,
            'geocoded_at' => now()->subDays(29),
        ]);

        $this->artisan('geocoding:purge-expired')->assertSuccessful();

        $this->assertNotNull($recent->refresh()->latitude);
    }

    public function test_keeps_own_coordinates_when_google_cache_expires(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->located()->confirmed(-16.7, -49.2)->create([
            'user_id' => $admin->id,
            'geocoded_at' => now()->subDays(45),
        ]);

        $this->artisan('geocoding:purge-expired')->assertSuccessful();

        $customer->refresh();
        $this->assertNull($customer->latitude);
        $this->assertSame(['lat' => -16.7, 'lng' => -49.2], $customer->coordinates());
        $this->assertSame(GeocodeStatus::Confirmed, $customer->geocode_status);
    }
}
