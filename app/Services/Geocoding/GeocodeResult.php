<?php

namespace App\Services\Geocoding;

final readonly class GeocodeResult
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $placeId,
        public string $locationType,
        public ?string $formattedAddress = null,
    ) {}

    /**
     * ROOFTOP = precisão de número. Os demais tipos indicam rua, bairro ou interpolação.
     */
    public function isPrecise(): bool
    {
        return $this->locationType === 'ROOFTOP';
    }
}
