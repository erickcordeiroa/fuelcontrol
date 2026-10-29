<?php

namespace App\Jobs;

use App\Enums\GeocodeStatus;
use App\Models\Customer;
use App\Services\Geocoding\GeocodeResult;
use App\Services\Geocoding\GeocoderFactory;
use App\Services\Geocoding\GeocodingException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/**
 * Busca a coordenada do cliente no Google somente quando não há coordenada própria nem cache válido.
 * Renova pelo place_id quando ele existe, para não refazer a busca por texto.
 */
class GeocodeCustomer implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public Customer $customer) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function uniqueId(): string
    {
        return (string) $this->customer->id;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('google-geocoding')];
    }

    public function handle(GeocoderFactory $geocoders): void
    {
        $customer = $this->customer->refresh();

        if (! $customer->needsGeocoding()) {
            return;
        }

        $geocoder = $geocoders->forTenant($customer->user_id);

        try {
            $result = $customer->google_place_id !== null
                ? ($geocoder->geocodePlaceId($customer->google_place_id, $customer) ?? $geocoder->geocode($customer->fullAddress(), $customer))
                : $geocoder->geocode($customer->fullAddress(), $customer);
        } catch (GeocodingException $e) {
            if ($e->retryable) {
                throw $e;
            }

            $this->markFailed($customer, $e->getMessage());

            return;
        }

        if ($result === null) {
            $this->markFailed($customer, __('Endereço não encontrado pelo Google.'));

            return;
        }

        $this->storeResult($customer, $result);
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception instanceof GeocodingException ? $exception->getMessage() : __('Falha ao geocodificar.');

        $this->markFailed($this->customer->refresh(), $message);
    }

    private function storeResult(Customer $customer, GeocodeResult $result): void
    {
        $customer->forceFill([
            'latitude' => round($result->latitude, 7),
            'longitude' => round($result->longitude, 7),
            'google_place_id' => $result->placeId ?? $customer->google_place_id,
            'location_type' => $result->locationType,
            'geocoded_at' => now(),
            'geocode_status' => $result->isPrecise() ? GeocodeStatus::Resolved : GeocodeStatus::Approximate,
            'geocode_error' => null,
        ])->save();
    }

    private function markFailed(Customer $customer, string $message): void
    {
        if (! $customer->needsGeocoding()) {
            return;
        }

        $customer->forceFill([
            'geocode_status' => GeocodeStatus::Failed,
            'geocode_error' => mb_substr($message, 0, 255),
        ])->save();
    }
}
