<?php

namespace App\Jobs;

use App\Enums\GeocodeStatus;
use App\Models\Customer;
use App\Models\GeocodedAddress;
use App\Services\Geocoding\AddressBook;
use App\Services\Geocoding\GeocodeResult;
use App\Services\Geocoding\GeocoderFactory;
use App\Services\Geocoding\GeocodingException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Str;
use Throwable;

/**
 * Localiza o cliente (spec 005/006), nesta ordem: coordenada própria → base de endereços já localizados
 * → provedor da plataforma, só se houver cota no mês e no teto diário da plataforma. Todo resultado do provedor entra na base.
 */
class GeocodeCustomer implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * Trava de "já está na fila" expira sozinha: se o job se perder, o cliente pode voltar para a fila depois.
     */
    public int $uniqueFor = 3600;

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
        return [new RateLimited('geocoding')];
    }

    public function handle(GeocoderFactory $geocoders, AddressBook $addressBook): void
    {
        $customer = $this->customer->refresh();

        if (! $customer->needsGeocoding()) {
            return;
        }

        $known = $addressBook->find($customer);

        if ($known?->hasCoordinates()) {
            $this->storeKnownAddress($customer, $known);

            return;
        }

        if ($known?->isKnownNotFound()) {
            $this->markFailed($customer, __('Endereço não encontrado. Clique em "Editar endereço" e marque o local no mapa.'));

            return;
        }

        if (! $geocoders->hasDailyCapacity()) {
            $customer->forceFill([
                'geocode_status' => GeocodeStatus::Pending,
                'geocode_error' => __('Limite diário de busca de endereços atingido. A busca continua automaticamente amanhã.'),
            ])->save();

            return;
        }

        if (! $geocoders->hasQuotaFor($customer->user_id)) {
            $customer->forceFill([
                'geocode_status' => GeocodeStatus::Pending,
                'geocode_error' => __('Cota mensal de busca de endereços atingida. Clique em "Editar endereço" e marque o local no mapa.'),
            ])->save();

            return;
        }

        $geocoder = $geocoders->forTenant($customer->user_id);
        $provider = $geocoders->provider();

        try {
            $result = $provider === 'google' && $customer->google_place_id !== null
                ? ($geocoder->geocodePlaceId($customer->google_place_id, $customer) ?? $geocoder->geocode($customer->fullAddress(), $customer))
                : $geocoder->geocode($customer->fullAddress(), $customer);
        } catch (GeocodingException $e) {
            if ($e->retryable) {
                throw $e;
            }

            $this->markFailed($customer, $e->getMessage());

            return;
        }

        if ($result === null || ! $this->isInCustomerCity($customer, $result)) {
            $addressBook->rememberNotFound($customer, $provider);
            $this->markFailed($customer, __('Endereço não encontrado. Clique em "Editar endereço" e marque o local no mapa.'));

            return;
        }

        $addressBook->rememberResult($customer, $result, $provider);
        $this->storeResult($customer, $result, $provider);
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception instanceof GeocodingException ? $exception->getMessage() : __('Falha ao localizar o endereço.');

        $this->markFailed($this->customer->refresh(), $message);
    }

    /**
     * Descarta resultado em outra cidade/UF quando o provedor informa onde fica (homônimos em outro município).
     */
    private function isInCustomerCity(Customer $customer, GeocodeResult $result): bool
    {
        $normalize = fn (?string $value): string => Str::of((string) $value)->ascii()->lower()->squish()->toString();

        if ($result->state !== null && $normalize($result->state) !== $normalize($customer->state)) {
            return false;
        }

        return $result->city === null || $normalize($result->city) === $normalize($customer->city);
    }

    private function storeKnownAddress(Customer $customer, GeocodedAddress $known): void
    {
        $customer->forceFill([
            'latitude' => $known->latitude,
            'longitude' => $known->longitude,
            'location_type' => $known->location_type,
            'geocode_provider' => 'address_book',
            'geocoded_at' => now(),
            'geocode_status' => $known->location_type === 'ROOFTOP' ? GeocodeStatus::Resolved : GeocodeStatus::Approximate,
            'geocode_error' => null,
        ])->save();
    }

    private function storeResult(Customer $customer, GeocodeResult $result, string $provider): void
    {
        $customer->forceFill([
            'latitude' => round($result->latitude, 7),
            'longitude' => round($result->longitude, 7),
            'google_place_id' => $provider === 'google' ? ($result->placeId ?? $customer->google_place_id) : $customer->google_place_id,
            'location_type' => $result->locationType,
            'geocode_provider' => $provider,
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
