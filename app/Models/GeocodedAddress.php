<?php

namespace App\Models;

use Database\Factories\GeocodedAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Endereço já localizado: guarda só endereço e coordenada. Sem empresa (user_id nulo) é o resultado do
 * provedor, compartilhado; com empresa é a posição confirmada por ela, usada só pelos clientes dela.
 */
class GeocodedAddress extends Model
{
    /** @use HasFactory<GeocodedAddressFactory> */
    use HasFactory;

    public const SOURCE_PROVIDER = 'provider';

    public const SOURCE_GPS = 'gps';

    public const SOURCE_MANUAL = 'manual';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'address_key',
        'street',
        'number',
        'district',
        'city',
        'state',
        'zip_code',
        'latitude',
        'longitude',
        'location_type',
        'source',
        'provider',
        'not_found_until',
        'confirmed_at',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'not_found_until' => 'datetime',
            'confirmed_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function isConfirmed(): bool
    {
        return in_array($this->source, [self::SOURCE_GPS, self::SOURCE_MANUAL], true);
    }

    public function isKnownNotFound(): bool
    {
        return ! $this->hasCoordinates() && $this->not_found_until?->isFuture() === true;
    }
}
