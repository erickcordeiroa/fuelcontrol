<?php

namespace App\Models;

use App\Enums\GeocodeStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Services\Geocoding\AddressBook;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Cliente final (destino das entregas). A coordenada pertence ao cliente, não ao pedido.
 *
 * Ordem de prioridade da posição: confirmed_* (GPS do motorista ou ajuste do admin, permanente)
 * → latitude/longitude (cache do Google, apagado após 30 dias) → nenhuma (precisa geocodificar).
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    /**
     * Dias que o cache de lat/lng do Google pode ser mantido (Google Maps Platform Terms).
     */
    public const GOOGLE_CACHE_DAYS = 30;

    /**
     * Esperando a busca há mais que isso, o processamento em segundo plano provavelmente está parado.
     */
    public const GEOCODE_STALE_MINUTES = 10;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'external_code',
        'name',
        'street',
        'number',
        'district',
        'city',
        'state',
        'zip_code',
        'reference',
        'phone',
        'address_hash',
        'geocode_status',
        'geocode_error',
        'confirmed_latitude',
        'confirmed_longitude',
        'confirmed_at',
        'google_place_id',
        'latitude',
        'longitude',
        'location_type',
        'geocoded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'geocode_status' => GeocodeStatus::class,
            'confirmed_latitude' => 'float',
            'confirmed_longitude' => 'float',
            'confirmed_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'geocoded_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * @param  array{street: string, number?: string|null, district?: string|null, city: string, state: string, zip_code: string}  $address
     */
    public static function hashAddress(array $address): string
    {
        $parts = [
            $address['street'],
            $address['number'] ?? '',
            $address['district'] ?? '',
            $address['city'],
            $address['state'],
            $address['zip_code'],
        ];

        $normalized = collect($parts)
            ->map(fn (?string $part): string => Str::of((string) $part)->ascii()->lower()->squish()->toString())
            ->implode('|');

        return hash('sha256', $normalized);
    }

    public function fullAddress(): string
    {
        $streetLine = trim($this->street.($this->number ? ', '.$this->number : ''));
        $zip = strlen($this->zip_code) === 8
            ? substr($this->zip_code, 0, 5).'-'.substr($this->zip_code, 5)
            : $this->zip_code;

        return collect([$streetLine, $this->district, $this->city.' - '.$this->state, $zip, 'Brasil'])
            ->filter()
            ->implode(', ');
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function coordinates(): ?array
    {
        if ($this->confirmed_latitude !== null && $this->confirmed_longitude !== null) {
            return ['lat' => $this->confirmed_latitude, 'lng' => $this->confirmed_longitude];
        }

        if ($this->latitude !== null && $this->longitude !== null) {
            return ['lat' => $this->latitude, 'lng' => $this->longitude];
        }

        return null;
    }

    public function hasCoordinates(): bool
    {
        return $this->coordinates() !== null;
    }

    /**
     * Só chama o Google quando não há coordenada própria nem cache válido.
     */
    public function needsGeocoding(): bool
    {
        if ($this->confirmed_latitude !== null && $this->confirmed_longitude !== null) {
            return false;
        }

        return $this->latitude === null || $this->longitude === null;
    }

    /**
     * Substitui a posição por uma coordenada própria (GPS da baixa ou ajuste do admin).
     */
    public function confirmLocation(float $latitude, float $longitude, GeocodeStatus $status = GeocodeStatus::Confirmed): void
    {
        $this->forceFill([
            'confirmed_latitude' => round($latitude, 7),
            'confirmed_longitude' => round($longitude, 7),
            'confirmed_at' => now(),
            'geocode_status' => $status,
            'geocode_error' => null,
        ])->save();

        app(AddressBook::class)->rememberConfirmed(
            $this,
            $latitude,
            $longitude,
            $status === GeocodeStatus::Confirmed ? GeocodedAddress::SOURCE_GPS : GeocodedAddress::SOURCE_MANUAL,
        );
    }

    /**
     * Endereço mudou: descarta todas as camadas de coordenada para refazer o ponto.
     */
    public function resetLocation(): void
    {
        $this->forceFill([
            'geocode_status' => GeocodeStatus::Pending,
            'geocode_error' => null,
            'confirmed_latitude' => null,
            'confirmed_longitude' => null,
            'confirmed_at' => null,
            'google_place_id' => null,
            'latitude' => null,
            'longitude' => null,
            'location_type' => null,
            'geocoded_at' => null,
        ]);
    }
}
