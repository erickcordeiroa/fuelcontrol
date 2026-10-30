<?php

namespace Tests\Feature\Deliveries;

use App\Livewire\Deliveries\DeliveryPlanner;
use App\Livewire\Deliveries\DepotSettings;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\GeocodingRequest;
use App\Models\User;
use App\Services\Routing\DeliveryRoutePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DepotSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_saves_the_depot_typing_coordinates_with_comma(): void
    {
        Livewire::actingAs($this->admin)
            ->test(DepotSettings::class)
            ->assertSee('Não cadastrado')
            ->call('edit')
            ->set('address', 'Av. Clara Gianotti de Souza, 100, Registro - SP')
            ->set('latitude', '-24,4960')
            ->set('longitude', '-47,8470')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('depot-updated')
            ->assertSee('as rotas saem e voltam daqui');

        $this->assertSame(['lat' => -24.496, 'lng' => -47.847], $this->admin->refresh()->depot());
    }

    public function test_locate_fills_coordinates_from_the_address_and_counts_one_lookup(): void
    {
        config(['services.geocoding.provider' => 'geoapify', 'services.geocoding.geoapify_key' => 'platform-key']);
        Http::fake(['api.geoapify.com/*' => Http::response(['results' => [[
            'lat' => -24.4961, 'lon' => -47.8471, 'formatted' => 'Avenida Clara Gianotti de Souza, 100, Registro - SP',
            'city' => 'Registro', 'state_code' => 'SP', 'place_id' => 'abc', 'rank' => ['match_type' => 'full_match'], 'result_type' => 'building',
        ]]])]);

        Livewire::actingAs($this->admin)
            ->test(DepotSettings::class)
            ->call('edit')
            ->set('address', 'Av. Clara Gianotti de Souza, 100, Registro - SP')
            ->call('locate')
            ->assertHasNoErrors()
            ->assertSet('latitude', '-24.4961')
            ->assertSet('longitude', '-47.8471');

        $this->assertSame(1, GeocodingRequest::query()->withoutGlobalScopes()->where('user_id', $this->admin->id)->count());
        $this->assertNull($this->admin->refresh()->depot());
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(DepotSettings::class)
            ->call('edit')
            ->set('latitude', '120')
            ->set('longitude', 'abc')
            ->call('save')
            ->assertHasErrors(['latitude', 'longitude']);
    }

    public function test_routes_leave_from_and_return_to_the_company_depot(): void
    {
        $this->admin->update(['depot_latitude' => -24.4960, 'depot_longitude' => -47.8470]);
        $other = User::factory()->admin()->create(['depot_latitude' => -10.0, 'depot_longitude' => -40.0]);
        $this->actingAs($this->admin);

        $delivery = Delivery::factory()->forTenant($this->admin->id)->create([
            'customer_id' => Customer::factory()->confirmed(-24.50, -47.84)->create(['user_id' => $this->admin->id]),
        ]);

        $route = app(DeliveryRoutePlanner::class)->create(['name' => 'Setor Sul', 'scheduled_for' => now()->toDateString()], collect([$delivery]));

        $this->assertSame(['lat' => -24.496, 'lng' => -47.847], $route->origin());
        $this->assertNotNull($route->return_path_polyline);
        $this->assertSame(['lat' => -10.0, 'lng' => -40.0], DeliveryRoute::defaultOrigin($other->id));
    }

    public function test_planner_page_shows_the_depot_card_and_redraws_the_map_after_saving(): void
    {
        $this->admin->update(['depot_latitude' => -24.4960, 'depot_longitude' => -47.8470]);

        $this->actingAs($this->admin)->get(route('deliveries.planner'))->assertOk()->assertSee('Centro de distribuição');

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->dispatch('depot-updated')
            ->assertDispatched('route-map-update')
            ->assertViewHas('mapPayload', fn (array $payload): bool => $payload['origin'] === ['lat' => -24.496, 'lng' => -47.847]);
    }

    public function test_driver_cannot_change_the_depot(): void
    {
        Livewire::actingAs(User::factory()->driverRole()->create())
            ->test(DepotSettings::class)
            ->assertForbidden();
    }
}
