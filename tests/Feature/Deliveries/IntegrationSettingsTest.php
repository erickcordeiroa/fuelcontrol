<?php

namespace Tests\Feature\Deliveries;

use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeCustomer;
use App\Livewire\Integrations\IntegrationSettings;
use App\Models\Customer;
use App\Models\GeocodingRequest;
use App\Models\GoogleMapsCredential;
use App\Models\User;
use App\Services\Geocoding\GeocoderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_KEY = 'AIzaSyTestKey1234567890abcdefghijklmno';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.google_maps.server_key' => null]);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_opens_integrations_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertSee('Google Maps · Geocoding API')
            ->assertSee('Não configurada');
    }

    public function test_driver_cannot_open_integrations(): void
    {
        $this->actingAs(User::factory()->driverRole()->create())
            ->get(route('integrations.index'))
            ->assertForbidden();
    }

    public function test_saving_a_working_key_stores_it_encrypted_and_queues_customers_without_location(): void
    {
        Queue::fake();
        $this->fakeGoogle('OK');
        $pending = Customer::factory()->create(['user_id' => $this->admin->id]);
        $failed = Customer::factory()->create(['user_id' => $this->admin->id, 'geocode_status' => GeocodeStatus::Failed, 'geocode_error' => 'Chave não cadastrada']);
        Customer::factory()->confirmed()->create(['user_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(IntegrationSettings::class)
            ->set('apiKey', self::VALID_KEY)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('apiKey', '')
            ->assertSee('••••••••lmno')
            ->assertDontSee(self::VALID_KEY);

        $credential = GoogleMapsCredential::forTenant($this->admin->id);
        $this->assertSame(self::VALID_KEY, $credential->api_key);
        $this->assertTrue($credential->isVerified());
        $this->assertNotSame(self::VALID_KEY, DB::table('google_maps_credentials')->value('api_key'));

        Http::assertSent(fn (Request $request): bool => $request['key'] === self::VALID_KEY);
        Queue::assertPushed(GeocodeCustomer::class, 2);
        $this->assertSame(GeocodeStatus::Pending, $failed->refresh()->geocode_status);
        $this->assertSame(GeocodeStatus::Pending, $pending->refresh()->geocode_status);
        $this->assertSame($this->admin->id, GeocodingRequest::query()->withoutGlobalScopes()->sole()->user_id);
    }

    public function test_rejected_key_keeps_the_error_and_queues_nothing(): void
    {
        Queue::fake();
        $this->fakeGoogle('REQUEST_DENIED', 'This API project is not authorized to use this API.');
        Customer::factory()->create(['user_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(IntegrationSettings::class)
            ->set('apiKey', self::VALID_KEY)
            ->call('save')
            ->assertSee('Com erro');

        $credential = GoogleMapsCredential::forTenant($this->admin->id);
        $this->assertFalse($credential->isVerified());
        $this->assertStringContainsString('not authorized', $credential->last_error);
        Queue::assertNothingPushed();
    }

    public function test_validates_key_format(): void
    {
        Livewire::actingAs($this->admin)
            ->test(IntegrationSettings::class)
            ->set('apiKey', 'chave com espaço')
            ->call('save')
            ->assertHasErrors(['apiKey' => 'regex']);

        $this->assertNull(GoogleMapsCredential::forTenant($this->admin->id));
        Http::assertNothingSent();
    }

    public function test_remove_key_disables_geocoding_for_the_tenant(): void
    {
        GoogleMapsCredential::factory()->forTenant($this->admin->id)->verified()->create();

        Livewire::actingAs($this->admin)
            ->test(IntegrationSettings::class)
            ->call('removeKey')
            ->assertSee('Não configurada');

        $this->assertFalse(app(GeocoderFactory::class)->isConfiguredFor($this->admin->id));
    }

    public function test_each_tenant_sees_and_uses_only_its_own_key(): void
    {
        $otherAdmin = User::factory()->admin()->create();
        GoogleMapsCredential::factory()->forTenant($otherAdmin->id)->verified()->create(['api_key' => 'AIzaOtherTenantKey000000000000000wxyz', 'key_hint' => 'wxyz']);

        Livewire::actingAs($this->admin)
            ->test(IntegrationSettings::class)
            ->assertViewHas('credential', null)
            ->assertDontSee('wxyz');

        $this->assertFalse(app(GeocoderFactory::class)->isConfiguredFor($this->admin->id));
        $this->assertTrue(app(GeocoderFactory::class)->isConfiguredFor($otherAdmin->id));
    }

    public function test_env_key_is_a_fallback_only_outside_production(): void
    {
        config(['services.google_maps.server_key' => 'AIzaLocalDevKey']);
        $factory = app(GeocoderFactory::class);

        $this->assertTrue($factory->isConfiguredFor($this->admin->id));

        $this->app['env'] = 'production';

        $this->assertFalse($factory->isConfiguredFor($this->admin->id));
    }

    public function test_planner_links_to_integrations_when_tenant_has_no_key(): void
    {
        $this->actingAs($this->admin)
            ->get(route('deliveries.planner'))
            ->assertOk()
            ->assertSee('Ir para Integrações')
            ->assertDontSee('Consumo do Google');
    }

    private function fakeGoogle(string $status, string $errorMessage = ''): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response([
            'status' => $status,
            'error_message' => $errorMessage,
            'results' => $status === 'OK'
                ? [['place_id' => 'ChIJse', 'geometry' => ['location' => ['lat' => -23.55, 'lng' => -46.63], 'location_type' => 'ROOFTOP']]]
                : [],
        ])]);
    }
}
