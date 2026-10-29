<?php

namespace Tests\Feature\Deliveries;

use App\Livewire\Integrations\IntegrationSettings;
use App\Models\Customer;
use App\Models\GeocodingRequest;
use App\Models\User;
use App\Services\Geocoding\GeoapifyGeocoder;
use App\Services\Geocoding\GeocoderFactory;
use App\Services\Geocoding\GoogleGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.geocoding.provider' => 'geoapify',
            'services.geocoding.geoapify_key' => 'platform-key',
            'plans.plans.inicial.limits.geocoding_per_month' => 100,
        ]);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_sees_usage_and_quota_without_any_key_field(): void
    {
        GeocodingRequest::factory()->forTenant($this->admin->id)->count(3)->create(['provider' => 'geoapify']);
        GeocodingRequest::factory()->forTenant($this->admin->id)->notBillable('HTTP_500')->create(['provider' => 'geoapify']);

        $this->actingAs($this->admin)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertSee('Busca de endereços')
            ->assertSee('Funcionando')
            ->assertSee('97 buscas restantes neste mês.')
            ->assertDontSee('Chave da API');
    }

    public function test_driver_cannot_open_integrations(): void
    {
        $this->actingAs(User::factory()->driverRole()->create())
            ->get(route('integrations.index'))
            ->assertForbidden();
    }

    public function test_shows_unavailable_when_platform_key_is_missing(): void
    {
        config(['services.geocoding.geoapify_key' => null]);

        Livewire::actingAs($this->admin)
            ->test(IntegrationSettings::class)
            ->assertViewHas('serviceAvailable', false)
            ->assertSee('Indisponível');
    }

    public function test_counts_customers_resolved_by_the_address_book(): void
    {
        Customer::factory()->create(['user_id' => $this->admin->id, 'geocode_provider' => 'address_book']);
        Customer::factory()->create(['user_id' => User::factory()->admin()->create()->id, 'geocode_provider' => 'address_book']);

        Livewire::actingAs($this->admin)
            ->test(IntegrationSettings::class)
            ->assertViewHas('reusedAddresses', 1);
    }

    public function test_factory_uses_the_configured_platform_provider(): void
    {
        $factory = app(GeocoderFactory::class);

        $this->assertInstanceOf(GeoapifyGeocoder::class, $factory->forTenant($this->admin->id));

        config(['services.geocoding.provider' => 'google']);

        $this->assertInstanceOf(GoogleGeocoder::class, $factory->forTenant($this->admin->id));
    }

    public function test_quota_counts_only_answered_requests_of_the_month(): void
    {
        config(['plans.plans.inicial.limits.geocoding_per_month' => 2]);
        $factory = app(GeocoderFactory::class);

        GeocodingRequest::factory()->forTenant($this->admin->id)->create(['provider' => 'geoapify']);
        GeocodingRequest::factory()->forTenant($this->admin->id)->notBillable('HTTP_500')->create(['provider' => 'geoapify']);
        GeocodingRequest::factory()->forTenant($this->admin->id)->create(['provider' => 'geoapify', 'created_at' => now()->subMonth()]);

        $this->assertTrue($factory->hasQuotaFor($this->admin->id));

        GeocodingRequest::factory()->forTenant($this->admin->id)->create(['provider' => 'geoapify']);

        $this->assertFalse($factory->hasQuotaFor($this->admin->id));
    }

    public function test_planner_explains_when_address_search_is_unavailable(): void
    {
        config(['services.geocoding.geoapify_key' => null]);

        $this->actingAs($this->admin)
            ->get(route('deliveries.planner'))
            ->assertOk()
            ->assertSee('Busca automática de endereços indisponível no momento.');
    }
}
