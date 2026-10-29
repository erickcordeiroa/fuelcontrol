<?php

namespace App\Services\Geocoding;

use App\Models\Customer;
use App\Models\GeocodingRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Geoapify Geocoding API (dados abertos): o resultado pode ser guardado e exibido no mapa OpenStreetMap.
 * Conta única da plataforma; cada consulta respondida fica registrada para a cota da empresa.
 */
class GeoapifyGeocoder implements Geocoder
{
    public const PROVIDER = 'geoapify';

    private const ENDPOINT = 'https://api.geoapify.com/v1/geocode/search';

    /** Tipos de resultado que localizam o número da casa. */
    private const PRECISE_TYPES = ['building', 'amenity'];

    /**
     * @param  int|null  $tenantId  Empresa que está consumindo a consulta
     */
    public function __construct(
        private readonly ?string $apiKey,
        private readonly ?int $tenantId = null,
    ) {}

    public function geocode(string $address, ?Customer $customer = null): ?GeocodeResult
    {
        if (blank($this->apiKey)) {
            throw GeocodingException::notConfigured();
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(10)
                ->retry([200, 1000], throw: false)
                ->get(self::ENDPOINT, [
                    'text' => $address,
                    'filter' => 'countrycode:br',
                    'lang' => 'pt',
                    'limit' => 1,
                    'format' => 'json',
                    'apiKey' => $this->apiKey,
                ]);
        } catch (ConnectionException $e) {
            /** A mensagem do cURL traz a URL com a chave: não pode ir para o banco nem para a tela. */
            report(new GeocodingException(str_replace($this->apiKey, '[redacted]', $e->getMessage())));

            throw new GeocodingException(__('Sem conexão com o serviço de endereços.'), retryable: true);
        }

        $this->record($response->status(), $response->successful(), $customer);

        if ($response->status() === 429 || $response->serverError()) {
            throw new GeocodingException(__('Serviço de endereços indisponível no momento (HTTP :status).', ['status' => $response->status()]), retryable: true);
        }

        if ($response->failed()) {
            throw new GeocodingException(__('O serviço de endereços recusou a consulta (HTTP :status).', ['status' => $response->status()]));
        }

        $result = $response->json('results.0');

        if (! is_array($result) || ! isset($result['lat'], $result['lon'])) {
            return null;
        }

        return new GeocodeResult(
            latitude: (float) $result['lat'],
            longitude: (float) $result['lon'],
            placeId: $result['place_id'] ?? null,
            locationType: $this->locationType($result),
            formattedAddress: $result['formatted'] ?? null,
            city: $result['city'] ?? null,
            state: $result['state_code'] ?? null,
        );
    }

    /**
     * Geoapify não usa place_id do Google; a renovação é uma nova busca pelo endereço.
     */
    public function geocodePlaceId(string $placeId, ?Customer $customer = null): ?GeocodeResult
    {
        return $customer === null ? null : $this->geocode($customer->fullAddress(), $customer);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function locationType(array $result): string
    {
        $type = (string) ($result['result_type'] ?? '');

        if (in_array($type, self::PRECISE_TYPES, true) && filled($result['housenumber'] ?? null)) {
            return 'ROOFTOP';
        }

        return $type === 'street' ? 'STREET' : 'APPROXIMATE';
    }

    private function record(int $httpStatus, bool $answered, ?Customer $customer): void
    {
        GeocodingRequest::query()->withoutGlobalScopes()->create([
            'user_id' => $this->tenantId ?? $customer?->user_id,
            'customer_id' => $customer?->id,
            'provider' => self::PROVIDER,
            'lookup' => GeocodingRequest::LOOKUP_ADDRESS,
            'provider_status' => $answered ? 'OK' : 'HTTP_'.$httpStatus,
            'billable' => $answered,
        ]);
    }
}
