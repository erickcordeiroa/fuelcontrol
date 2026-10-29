<?php

namespace Tests\Feature\Deliveries;

use App\Models\Customer;
use App\Models\GeocodingRequest;
use App\Models\User;
use App\Services\Geocoding\GeoapifyGeocoder;
use App\Services\Geocoding\GeocodingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoapifyGeocoderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_house_number_result_is_precise_and_carries_city_and_state(): void
    {
        $this->fakeResult(['lat' => -24.4927, 'lon' => -47.8436, 'result_type' => 'building', 'housenumber' => '410', 'city' => 'Registro', 'state_code' => 'SP', 'formatted' => 'Rua Shitiro Maeji, 410, Registro - SP']);

        $result = (new GeoapifyGeocoder('platform-key'))->geocode('Rua Shitiro Maeji, 410, Registro - SP');

        $this->assertSame(-24.4927, $result->latitude);
        $this->assertSame(-47.8436, $result->longitude);
        $this->assertTrue($result->isPrecise());
        $this->assertSame('Registro', $result->city);
        $this->assertSame('SP', $result->state);
        Http::assertSent(fn (Request $request): bool => $request['apiKey'] === 'platform-key'
            && $request['filter'] === 'countrycode:br'
            && str_contains($request['text'], 'Shitiro'));
    }

    public function test_street_level_result_is_not_precise(): void
    {
        $this->fakeResult(['lat' => -24.49, 'lon' => -47.84, 'result_type' => 'street', 'city' => 'Registro', 'state_code' => 'SP']);

        $result = (new GeoapifyGeocoder('platform-key'))->geocode('Rua Shitiro Maeji, 999, Registro - SP');

        $this->assertSame('STREET', $result->locationType);
        $this->assertFalse($result->isPrecise());
    }

    public function test_empty_result_returns_null_and_counts_in_the_quota(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['user_id' => $admin->id]);
        Http::fake(['api.geoapify.com/*' => Http::response(['results' => []])]);

        $this->assertNull((new GeoapifyGeocoder('platform-key', $admin->id))->geocode('Rua Nada, 1', $customer));

        $request = GeocodingRequest::query()->withoutGlobalScopes()->sole();
        $this->assertSame('geoapify', $request->provider);
        $this->assertSame($admin->id, $request->user_id);
        $this->assertSame($customer->id, $request->customer_id);
        $this->assertTrue($request->billable);
    }

    public function test_rate_limit_is_retryable_and_not_counted(): void
    {
        Http::fake(['api.geoapify.com/*' => Http::response(['message' => 'Too Many Requests'], 429)]);

        try {
            (new GeoapifyGeocoder('platform-key'))->geocode('Rua 1');
            $this->fail('Era esperada uma GeocodingException.');
        } catch (GeocodingException $e) {
            $this->assertTrue($e->retryable);
        }

        $this->assertFalse(GeocodingRequest::query()->withoutGlobalScopes()->sole()->billable);
    }

    public function test_invalid_key_is_not_retryable(): void
    {
        Http::fake(['api.geoapify.com/*' => Http::response(['message' => 'Invalid apiKey'], 401)]);

        try {
            (new GeoapifyGeocoder('wrong-key'))->geocode('Rua 1');
            $this->fail('Era esperada uma GeocodingException.');
        } catch (GeocodingException $e) {
            $this->assertFalse($e->retryable);
            $this->assertStringNotContainsString('wrong-key', $e->getMessage());
        }
    }

    public function test_connection_error_does_not_expose_the_key(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://api.geoapify.com/v1/geocode/search?apiKey=secret-platform-key'));

        try {
            (new GeoapifyGeocoder('secret-platform-key'))->geocode('Rua 1');
            $this->fail('Era esperada uma GeocodingException.');
        } catch (GeocodingException $e) {
            $this->assertTrue($e->retryable);
            $this->assertStringNotContainsString('secret-platform-key', $e->getMessage());
        }

        $this->assertSame(0, GeocodingRequest::query()->withoutGlobalScopes()->count());
    }

    public function test_missing_key_does_not_call_the_provider(): void
    {
        $this->expectException(GeocodingException::class);

        try {
            (new GeoapifyGeocoder(null))->geocode('Rua 1');
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function fakeResult(array $result): void
    {
        Http::fake(['api.geoapify.com/*' => Http::response(['results' => [$result]])]);
    }
}
