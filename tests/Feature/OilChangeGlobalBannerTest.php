<?php

namespace Tests\Feature;

use App\Models\OilChange;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OilChangeGlobalBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_oil_alert_banner_appears_on_pages_using_app_layout(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create([
            'user_id' => $admin->id,
            'plate' => 'ABC-1D23',
        ]);
        OilChange::factory()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'date' => CarbonImmutable::now()->subDays(155)->toDateString(),
            'interval_km' => 10000,
        ]);
        Cache::flush();

        $this->actingAs($admin);

        $response = $this->get(route('vehicles.index'));

        $response->assertOk();
        $response->assertSee('Trocas de óleo pendentes', false);
        $response->assertSee('ABC-1D23', false);
    }
}
