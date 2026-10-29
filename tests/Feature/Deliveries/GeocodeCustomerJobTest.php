<?php

namespace Tests\Feature\Deliveries;

use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeCustomer;
use App\Models\Customer;
use App\Models\User;
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

    public function test_customer_with_own_coordinates_never_calls_google(): void
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
        $this->assertSame('Endereço não encontrado pelo Google.', $customer->geocode_error);
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
        $this->assertSame('Falha ao geocodificar.', $customer->geocode_error);
    }

    private function runJob(Customer $customer): void
    {
        (new GeocodeCustomer($customer))->handle(app(GeocoderFactory::class));
    }

    /**
     * Substitui o geocoder entregue pela fábrica (a chave real vem de Integrações).
     */
    private function mockGeocoder(callable $expectations): void
    {
        $geocoder = Mockery::mock(Geocoder::class);
        $expectations($geocoder);

        $this->mock(GeocoderFactory::class, fn (MockInterface $mock) => $mock->allows('forTenant')->andReturn($geocoder));
    }
}
