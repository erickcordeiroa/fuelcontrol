<?php

namespace Tests\Feature\Deliveries;

use App\Models\Customer;
use App\Models\GeocodingRequest;
use App\Models\User;
use App\Services\Geocoding\GeocodingException;
use App\Services\Geocoding\GoogleGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleGeocoderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_returns_coordinates_place_id_and_precision(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'place_id' => 'ChIJabc123',
                    'formatted_address' => 'R. das Acácias, 410 - Jardim América, Goiânia - GO',
                    'geometry' => [
                        'location' => ['lat' => -16.7123456, 'lng' => -49.2654321],
                        'location_type' => 'ROOFTOP',
                    ],
                ]],
            ]),
        ]);

        $result = (new GoogleGeocoder('test-key'))->geocode('Rua das Acácias, 410, Goiânia - GO');

        $this->assertNotNull($result);
        $this->assertSame(-16.7123456, $result->latitude);
        $this->assertSame(-49.2654321, $result->longitude);
        $this->assertSame('ChIJabc123', $result->placeId);
        $this->assertTrue($result->isPrecise());

        Http::assertSent(fn (Request $request): bool => $request['key'] === 'test-key'
            && $request['components'] === 'country:BR'
            && str_contains($request['address'], 'Acácias'));
    }

    public function test_geocode_by_place_id_sends_place_id(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'place_id' => 'ChIJabc123',
                    'geometry' => ['location' => ['lat' => -16.7, 'lng' => -49.2], 'location_type' => 'GEOMETRIC_CENTER'],
                ]],
            ]),
        ]);

        $result = (new GoogleGeocoder('test-key'))->geocodePlaceId('ChIJabc123');

        $this->assertFalse($result->isPrecise());
        Http::assertSent(fn (Request $request): bool => $request['place_id'] === 'ChIJabc123');
    }

    public function test_zero_results_returns_null(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []])]);

        $this->assertNull((new GoogleGeocoder('test-key'))->geocode('Endereço que não existe'));
    }

    public function test_denied_request_throws_non_retryable_exception(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'REQUEST_DENIED', 'error_message' => 'API key inválida'])]);

        try {
            (new GoogleGeocoder('bad-key'))->geocode('Rua 1');
            $this->fail('Era esperada uma GeocodingException.');
        } catch (GeocodingException $e) {
            $this->assertFalse($e->retryable);
            $this->assertStringContainsString('API key inválida', $e->getMessage());
        }
    }

    public function test_quota_exceeded_is_retryable(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'OVER_QUERY_LIMIT'])]);

        try {
            (new GoogleGeocoder('test-key'))->geocode('Rua 1');
            $this->fail('Era esperada uma GeocodingException.');
        } catch (GeocodingException $e) {
            $this->assertTrue($e->retryable);
        }
    }

    public function test_missing_key_throws_without_calling_google(): void
    {
        $this->expectException(GeocodingException::class);

        try {
            (new GoogleGeocoder(null))->geocode('Rua 1');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_records_billable_request_with_customer_and_tenant(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['user_id' => $admin->id]);
        Http::fake(['maps.googleapis.com/*' => Http::response([
            'status' => 'OK',
            'results' => [['place_id' => 'ChIJabc', 'geometry' => ['location' => ['lat' => -24.49, 'lng' => -47.84], 'location_type' => 'ROOFTOP']]],
        ])]);

        (new GoogleGeocoder('test-key'))->geocode('Rua Shitiro Maeji, 410, Registro - SP', $customer);

        $request = GeocodingRequest::query()->withoutGlobalScopes()->sole();
        $this->assertSame($admin->id, $request->user_id);
        $this->assertSame($customer->id, $request->customer_id);
        $this->assertSame(GeocodingRequest::LOOKUP_ADDRESS, $request->lookup);
        $this->assertSame('OK', $request->provider_status);
        $this->assertTrue($request->billable);
    }

    public function test_zero_results_is_recorded_as_billable(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []])]);

        (new GoogleGeocoder('test-key'))->geocodePlaceId('ChIJgone');

        $request = GeocodingRequest::query()->withoutGlobalScopes()->sole();
        $this->assertSame(GeocodingRequest::LOOKUP_PLACE_ID, $request->lookup);
        $this->assertTrue($request->billable);
    }

    public function test_errors_are_recorded_as_not_billable(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::sequence()
            ->push(['status' => 'OVER_QUERY_LIMIT'])
            ->push('erro', 500)
            ->push('erro', 500)
            ->push('erro', 500)]);

        $geocoder = new GoogleGeocoder('test-key');

        foreach (['Rua 1', 'Rua 2'] as $address) {
            try {
                $geocoder->geocode($address);
            } catch (GeocodingException) {
            }
        }

        $requests = GeocodingRequest::query()->withoutGlobalScopes()->orderBy('id')->get();
        $this->assertSame(['OVER_QUERY_LIMIT', 'HTTP_500'], $requests->pluck('provider_status')->all());
        $this->assertFalse($requests->contains('billable', true));
    }

    public function test_missing_key_records_nothing(): void
    {
        try {
            (new GoogleGeocoder(null))->geocode('Rua 1');
        } catch (GeocodingException) {
        }

        $this->assertSame(0, GeocodingRequest::query()->withoutGlobalScopes()->count());
    }

    public function test_connection_error_message_does_not_expose_the_api_key(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://maps.googleapis.com/maps/api/geocode/json?key=secret-key-123'));

        try {
            (new GoogleGeocoder('secret-key-123'))->geocode('Rua 1');
            $this->fail('Era esperada uma GeocodingException.');
        } catch (GeocodingException $e) {
            $this->assertTrue($e->retryable);
            $this->assertStringNotContainsString('secret-key-123', $e->getMessage());
        }
    }
}
