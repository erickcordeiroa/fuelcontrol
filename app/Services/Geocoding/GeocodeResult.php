<?php

namespace App\Services\Geocoding;

final readonly class GeocodeResult
{
    /**
     * @param  string|null  $city  Cidade devolvida pelo provedor, quando ele informa (usada para descartar resultado em outra cidade)
     * @param  string|null  $state  UF devolvida pelo provedor, quando ele informa
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $placeId,
        public string $locationType,
        public ?string $formattedAddress = null,
        public ?string $city = null,
        public ?string $state = null,
    ) {}

    /**
     * ROOFTOP = precisão de número. Os demais tipos indicam rua, bairro ou interpolação.
     */
    public function isPrecise(): bool
    {
        return $this->locationType === 'ROOFTOP';
    }
}
