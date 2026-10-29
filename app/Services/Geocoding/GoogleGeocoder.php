<?php

namespace App\Services\Geocoding;

use App\Models\Customer;
use App\Models\GeocodingRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Geocoding API. Os termos permitem guardar lat/lng por até 30 dias; o place_id não tem prazo.
 * Cada consulta respondida pelo Google fica registrada em geocoding_requests para acompanhar a fatura.
 */
class GoogleGeocoder implements Geocoder
{
    private const ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * @param  int|null  $tenantId  Empresa dona da chave, usada para registrar o consumo
     */
    public function __construct(
        private readonly ?string $apiKey,
        private readonly ?int $tenantId = null,
    ) {}

    /** Status que entram na fatura do Google (mesmo sem resultado). */
    private const BILLABLE_STATUSES = ['OK', 'ZERO_RESULTS', 'NOT_FOUND'];

    public function geocode(string $address, ?Customer $customer = null): ?GeocodeResult
    {
        return $this->request([
            'address' => $address,
            'components' => 'country:BR',
            'region' => 'br',
        ], GeocodingRequest::LOOKUP_ADDRESS, $customer);
    }

    public function geocodePlaceId(string $placeId, ?Customer $customer = null): ?GeocodeResult
    {
        return $this->request(['place_id' => $placeId], GeocodingRequest::LOOKUP_PLACE_ID, $customer);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function request(array $query, string $lookup, ?Customer $customer): ?GeocodeResult
    {
        if (blank($this->apiKey)) {
            throw GeocodingException::notConfigured();
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(10)
                ->retry([200, 1000], throw: false)
                ->get(self::ENDPOINT, [...$query, 'language' => 'pt-BR', 'key' => $this->apiKey]);
        } catch (ConnectionException $e) {
            /** A mensagem do cURL traz a URL com a chave: não pode ir para o banco nem para a tela. */
            report(new GeocodingException(str_replace($this->apiKey, '[redacted]', $e->getMessage())));

            throw new GeocodingException(__('Sem conexão com o Google.'), retryable: true);
        }

        if ($response->failed()) {
            $this->record($lookup, 'HTTP_'.$response->status(), $customer);

            throw new GeocodingException(__('Google respondeu HTTP :status.', ['status' => $response->status()]), retryable: $response->serverError());
        }

        $status = (string) $response->json('status');
        $this->record($lookup, $status, $customer);

        return match ($status) {
            'OK' => $this->toResult($response->json('results.0')),
            'ZERO_RESULTS', 'NOT_FOUND' => null,
            'OVER_QUERY_LIMIT', 'UNKNOWN_ERROR' => throw new GeocodingException(__('Google indisponível no momento (:status).', ['status' => $status]), retryable: true),
            default => throw new GeocodingException(__('Google recusou a consulta (:status): :message', [
                'status' => $status,
                'message' => (string) $response->json('error_message', ''),
            ])),
        };
    }

    private function record(string $lookup, string $status, ?Customer $customer): void
    {
        GeocodingRequest::query()->withoutGlobalScopes()->create([
            'user_id' => $this->tenantId ?? $customer?->user_id,
            'customer_id' => $customer?->id,
            'lookup' => $lookup,
            'provider_status' => mb_substr($status, 0, 32),
            'billable' => in_array($status, self::BILLABLE_STATUSES, true),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function toResult(?array $result): ?GeocodeResult
    {
        if ($result === null) {
            return null;
        }

        return new GeocodeResult(
            latitude: (float) data_get($result, 'geometry.location.lat'),
            longitude: (float) data_get($result, 'geometry.location.lng'),
            placeId: data_get($result, 'place_id'),
            locationType: (string) data_get($result, 'geometry.location_type', 'APPROXIMATE'),
            formattedAddress: data_get($result, 'formatted_address'),
        );
    }
}
