<?php

namespace App\Services\Geocoding;

use App\Models\GoogleMapsCredential;

/**
 * Monta o geocoder com a chave da empresa. Fora de produção, sem chave cadastrada,
 * usa GOOGLE_MAPS_SERVER_KEY do .env para facilitar o desenvolvimento.
 */
class GeocoderFactory
{
    public function forTenant(?int $tenantId): Geocoder
    {
        return new GoogleGeocoder($this->apiKeyFor($tenantId), $tenantId);
    }

    public function withKey(string $apiKey, ?int $tenantId): Geocoder
    {
        return new GoogleGeocoder($apiKey, $tenantId);
    }

    public function isConfiguredFor(?int $tenantId): bool
    {
        return filled($this->apiKeyFor($tenantId));
    }

    private function apiKeyFor(?int $tenantId): ?string
    {
        $tenantKey = GoogleMapsCredential::forTenant($tenantId)?->api_key;

        if (filled($tenantKey)) {
            return $tenantKey;
        }

        return app()->isProduction() ? null : config('services.google_maps.server_key');
    }
}
