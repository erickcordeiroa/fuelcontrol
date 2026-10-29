<?php

namespace Tests\Feature\Deliveries;

use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeCustomer;
use App\Models\Customer;
use App\Models\GeocodedAddress;
use App\Models\GeocodingRequest;
use App\Models\User;
use App\Services\Geocoding\AddressBook;
use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\GeocodeResult;
use App\Services\Geocoding\GeocoderFactory;
use App\Services\Geocoding\GeocodingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GeocodeCustomerJobTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_stores_google_cache_and_marks_precise_result_as_resolved(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->admin->id]);

        $this->mockGeocoder(function (MockInterface $mock) use ($customer): void {
            $mock->shouldReceive('geocode')
                ->once()
                ->with($customer->fullAddress(), Mockery::type(Customer::class))
                ->andReturn(new GeocodeResult(-16.71, -49.26, 'ChIJxyz', 'ROOFTOP'));
        });

        $this->runJob($customer);

        $customer->refresh();
        $this->assertSame(GeocodeStatus::Resolved, $customer->geocode_status);
        $this->assertSame(-16.71, $customer->latitude);
        $this->assertSame(-49.26, $customer->longitude);
        $this->assertSame('ChIJxyz', $customer->google_place_id);
        $this->assertNotNull($customer->geocoded_at);
        $this->assertNull($customer->confirmed_latitude);
    }

    public function test_imprecise_result_goes_to_review(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->admin->id]);

        $this->mockGeocoder(fn (MockInterface $mock) => $mock->shouldReceive('geocode')->andReturn(
            new GeocodeResult(-16.7, -49.2, 'ChIJxyz', 'GEOMETRIC_CENTER'),
        ));

        $this->runJob($customer);

        $this->assertSame(GeocodeStatus::Approximate, $customer->refresh()->geocode_status);
    }

    public function test_customer_with_own_coordinates_never_calls_the_provider(): void
    {
        $customer = Customer::factory()->confirmed()->create(['user_id' => $this->admin->id]);

        $this->mockGeocoder(fn (MockInterface $mock) => $mock->shouldNotReceive('geocode', 'geocodePlaceId'));

        $this->runJob($customer);

        $this->assertSame(GeocodeStatus::Confirmed, $customer->refresh()->geocode_status);
    }

    public function test_expired_cache_is_renewed_by_place_id(): void
    {
        $customer = Customer::factory()->create([
            'user_id' => $this->admin->id,
            'google_place_id' => 'ChIJkeep',
        ]);

        $this->mockGeocoder(function (MockInterface $mock): void {
            $mock->shouldReceive('geocodePlaceId')->once()->with('ChIJkeep', Mockery::type(Customer::class))->andReturn(new GeocodeResult(-16.7, -49.2, 'ChIJkeep', 'ROOFTOP'));
            $mock->shouldNotReceive('geocode');
        });

        $this->runJob($customer);

        $this->assertSame(-16.7, $customer->refresh()->latitude);
    }

    public function test_address_not_found_marks_failed(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->admin->id]);

        $this->mockGeocoder(fn (MockInterface $mock) => $mock->shouldReceive('geocode')->andReturnNull());

        $this->runJob($customer);

        $customer->refresh();
        $this->assertSame(GeocodeStatus::Failed, $customer->geocode_status);
        $this->assertStringStartsWith('Endereço não encontrado.', $customer->geocode_error);
    }

    public function test_configuration_error_marks_failed_without_retrying(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->admin->id]);

        $this->mockGeocoder(fn (MockInterface $mock) => $mock->shouldReceive('geocode')->andThrow(GeocodingException::notConfigured()));

        $this->runJob($customer);

        $this->assertSame(GeocodeStatus::Failed, $customer->refresh()->geocode_status);
    }

    public function test_temporary_error_is_rethrown_for_retry(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->admin->id]);

        $this->mockGeocoder(fn (MockInterface $mock) => $mock->shouldReceive('geocode')->andThrow(new GeocodingException('Indisponível', retryable: true)));

        $this->expectException(GeocodingException::class);

        $this->runJob($customer);
    }

    public function test_unexpected_failure_stores_a_generic_message(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->admin->id]);

        (new GeocodeCustomer($customer))->failed(new \RuntimeException('https://maps.googleapis.com/...?key=secret-key-123'));

        $this->assertSame(GeocodeStatus::Failed, $customer->refresh()->geocode_status);
        $this->assertSame('Falha ao localizar o endereço.', $customer->geocode_error);
    }

    public function test_known_address_is_reused_without_calling_the_provider(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->admin->id, 'street' => 'R. Shitiro Maeji', 'number' => '410', 'city' => 'Registro', 'state' => 'SP']);
        GeocodedAddress::factory()->create([
            'address_key' => AddressBook::keyFor(['street' => 'Rua Shitiro Maeji', 'number' => '410', 'city' => 'REGISTRO', 'state' => 'sp']),
            'latitude' => -24.4927,
            'longitude' => -47.8436,
            'location_type' => 'ROOFTOP',
        ]);

        $this->mockGeocoder(fn (MockInterface $mock) => $mock->shouldNotReceive('geocode', 'geocodePlaceId'));

        $this->runJob($customer);

        $customer->refresh();
        $this->assertSame(GeocodeStatus::Resolved, $customer->geocode_status);
        $this->assertSame(['lat' => -24.4927, 'lng' => -47.8436], $customer->coordinates());
        $this->assertSame('address_book', $customer->geocode_provider);
    }

    public function test_provider_result_is_saved_in_the_address_book(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->admin->id]);

        $this->mockGeocoder(fn (MockInterface $mock) => $mock->shouldReceive('geocode')->once()->andReturn(
            new GeocodeResult(-24.49, -47.84, null, 'STREET', null, 'Registro', 'SP'),
        ), provider: 'geoapify');

        $this->runJob($customer);

        $entry = GeocodedAddress::query()->sole();
        $this->assertSame(-24.49, $entry->latitude);
        $this->assertSame('STREET', $entry->location_type);
        $this->assertSame('geoapify', $entry->provider);
        $this->assertSame(GeocodeStatus::Approximate, $customer->refresh()->geocode_status);
        $this->assertSame('geoapify', $customer->geocode_provider);
    }

    public function test_same_address_for_another_company_uses_the_address_book(): void
    {
        $otherAdmin = User::factory()->admin()->create();
        $address = ['street' => 'Avenida Wild José de Souza', 'number' => '2162', 'district' => 'Vila Tupi', 'city' => 'Registro', 'state' => 'SP'];
        $first = Customer::factory()->create(['user_id' => $this->admin->id, ...$address]);
        $second = Customer::factory()->create(['user_id' => $otherAdmin->id, ...$address, 'street' => 'Av. Wild Jose de Souza']);

        $this->mockGeocoder(fn (MockInterface $mock) => $mock->shouldReceive('geocode')->once()->andReturn(
            new GeocodeResult(-24.4988, -47.8471, null, 'ROOFTOP'),
        ), provider: 'geoapify');

        $this->runJob($first);
        $this->runJob($second);

        $this->assertSame(['lat' => -24.4988, 'lng' => -47.8471], $second->refresh()->coordinates());
        $this->assertSame(1, GeocodedAddress::query()->count());
    }

    public function test_not_found_address_is_not_queried_again(): void
    {
        $address = ['street' => 'Rua Inexistente', 'number' => '1', 'city' => 'Registro', 'state' => 'SP'];
        $first = Customer::factory()->create(['user_id' => $this->admin->id, ...$address]);
        $second = Customer::factory()->create(['user_id' => $this->admin->id, ...$address]);

        $this->mockGeocoder(fn (MockInterface $mock) => $mock->shouldReceive('geocode')->once()->andReturnNull(), provider: 'geoapify');

        $this->runJob($first);
        $this->runJob($second);

        $this->assertSame(GeocodeStatus::Failed, $second->refresh()->geocode_status);
        $this->assertTrue(GeocodedAddress::query()->sole()->isKnownNotFound());
    }

    public function test_result_in_another_city_is_discarded(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->admin->id, 'city' => 'Registro', 'state' => 'SP']);

        $this->mockGeocoder(fn (MockInterface $mock) => $mock->shouldReceive('geocode')->andReturn(
            new GeocodeResult(-24.55, -48.05, null, 'STREET', null, 'Sete Barras', 'SP'),
        ), provider: 'geoapify');

        $this->runJob($customer);

        $customer->refresh();
        $this->assertSame(GeocodeStatus::Failed, $customer->geocode_status);
        $this->assertNull($customer->latitude);
    }

    public function test_quota_exceeded_keeps_customer_pending_without_calling_the_provider(): void
    {
        config(['plans.plans.inicial.limits.geocoding_per_month' => 2]);
        GeocodingRequest::factory()->forTenant($this->admin->id)->count(2)->create(['provider' => 'geoapify']);
        $customer = Customer::factory()->create(['user_id' => $this->admin->id]);

        $geocoder = Mockery::mock(Geocoder::class);
        $geocoder->shouldNotReceive('geocode');
        $this->app->instance(GeocoderFactory::class, Mockery::mock(GeocoderFactory::class.'[forTenant]', fn (MockInterface $mock) => $mock->allows('forTenant')->andReturn($geocoder))->makePartial());

        $this->runJob($customer);

        $customer->refresh();
        $this->assertSame(GeocodeStatus::Pending, $customer->geocode_status);
        $this->assertStringContainsString('Cota mensal', $customer->geocode_error);
    }

    public function test_confirmed_location_is_reused_only_by_the_same_company(): void
    {
        $address = ['street' => 'Rua Capitão João Pocci', 'number' => '221', 'city' => 'Registro', 'state' => 'SP'];
        $confirmed = Customer::factory()->located(-24.40, -47.80)->create(['user_id' => $this->admin->id, ...$address]);
        $confirmed->confirmLocation(-24.4933, -47.8469);

        $entry = GeocodedAddress::query()->sole();
        $this->assertSame($this->admin->id, $entry->user_id);
        $this->assertSame(GeocodedAddress::SOURCE_GPS, $entry->source);

        $sameCompany = Customer::factory()->create(['user_id' => $this->admin->id, ...$address]);
        $this->mockGeocoder(fn (MockInterface $mock) => $mock->shouldNotReceive('geocode'), provider: 'geoapify');
        $this->runJob($sameCompany);

        $this->assertSame(['lat' => -24.4933, 'lng' => -47.8469], $sameCompany->refresh()->coordinates());
    }

    public function test_another_company_never_receives_a_confirmed_position_it_did_not_make(): void
    {
        $address = ['street' => 'Rua Capitão João Pocci', 'number' => '221', 'city' => 'Registro', 'state' => 'SP'];
        Customer::factory()->located(-24.40, -47.80)->create(['user_id' => $this->admin->id, ...$address])
            ->confirmLocation(-10.0, -10.0, GeocodeStatus::Manual);

        $otherCompanyCustomer = Customer::factory()->create(['user_id' => User::factory()->admin()->create()->id, ...$address]);
        $this->mockGeocoder(fn (MockInterface $mock) => $mock->shouldReceive('geocode')->once()->andReturn(
            new GeocodeResult(-24.4933, -47.8469, null, 'ROOFTOP'),
        ), provider: 'geoapify');

        $this->runJob($otherCompanyCustomer);

        $this->assertSame(['lat' => -24.4933, 'lng' => -47.8469], $otherCompanyCustomer->refresh()->coordinates());
        $this->assertNotNull(GeocodedAddress::query()->whereNull('user_id')->first());
    }

    private function runJob(Customer $customer): void
    {
        (new GeocodeCustomer($customer))->handle(app(GeocoderFactory::class), app(AddressBook::class));
    }

    /**
     * Substitui o geocoder entregue pela fábrica; a cota é liberada salvo quando o teste diz o contrário.
     */
    private function mockGeocoder(callable $expectations, string $provider = 'google'): void
    {
        $geocoder = Mockery::mock(Geocoder::class);
        $expectations($geocoder);

        $this->mock(GeocoderFactory::class, function (MockInterface $mock) use ($geocoder, $provider): void {
            $mock->allows('forTenant')->andReturn($geocoder);
            $mock->allows('provider')->andReturn($provider);
            $mock->allows('hasQuotaFor')->andReturn(true);
            $mock->allows('hasDailyCapacity')->andReturn(true);
        });
    }
}
