<?php

namespace App\Services\Geocoding;

use App\Models\Customer;
use App\Models\GeocodedAddress;
use Illuminate\Support\Str;

/**
 * Base de endereços já localizados (spec 006). Consultada antes de qualquer chamada ao provedor.
 *
 * Resultados do provedor são compartilhados entre empresas (é onde está a economia). Posições
 * confirmadas por GPS ou pino valem só para a empresa que confirmou: uma empresa nunca altera a
 * posição usada pelos clientes de outra.
 */
class AddressBook
{
    /** Por quanto tempo um endereço "não encontrado" deixa de ser consultado de novo. */
    public const NOT_FOUND_DAYS = 30;

    /** @var array<string, string> */
    private const STREET_ABBREVIATIONS = [
        'r' => 'rua',
        'av' => 'avenida',
        'al' => 'alameda',
        'tv' => 'travessa',
        'trav' => 'travessa',
        'rod' => 'rodovia',
        'est' => 'estrada',
        'pc' => 'praca',
        'pca' => 'praca',
    ];

    /**
     * Chave do endereço: logradouro, número, cidade e UF normalizados.
     * Bairro e CEP ficam fora da chave porque variam muito entre planilhas para o mesmo lugar.
     *
     * @param  array{street: string, number?: string|null, city: string, state: string}  $address
     */
    public static function keyFor(array $address): string
    {
        $street = collect(explode(' ', self::normalize($address['street'])))
            ->map(fn (string $word): string => self::STREET_ABBREVIATIONS[$word] ?? $word)
            ->implode(' ');

        $number = preg_replace('/\D+/', '', (string) ($address['number'] ?? '')) ?: 'sn';

        return hash('sha256', implode('|', [$street, $number, self::normalize($address['city']), self::normalize($address['state'])]));
    }

    /**
     * A posição confirmada pela própria empresa tem prioridade; depois, o resultado compartilhado do provedor.
     */
    public function find(Customer $customer): ?GeocodedAddress
    {
        $key = self::keyFor($this->addressOf($customer));

        $own = $customer->user_id === null ? null : GeocodedAddress::query()
            ->where('address_key', $key)
            ->where('user_id', $customer->user_id)
            ->first();

        $entry = $own?->hasCoordinates()
            ? $own
            : GeocodedAddress::query()->where('address_key', $key)->whereNull('user_id')->first();

        $entry?->forceFill(['last_used_at' => now()])->save();

        return $entry;
    }

    public function rememberResult(Customer $customer, GeocodeResult $result, string $provider): GeocodedAddress
    {
        $entry = $this->entryFor($customer, null);

        $entry->fill([
            'latitude' => round($result->latitude, 7),
            'longitude' => round($result->longitude, 7),
            'location_type' => $result->locationType,
            'source' => GeocodedAddress::SOURCE_PROVIDER,
            'provider' => $provider,
            'not_found_until' => null,
            'last_used_at' => now(),
        ])->save();

        return $entry;
    }

    public function rememberNotFound(Customer $customer, string $provider): void
    {
        $entry = $this->entryFor($customer, null);

        if ($entry->exists && $entry->hasCoordinates()) {
            return;
        }

        $entry->fill([
            'source' => GeocodedAddress::SOURCE_PROVIDER,
            'provider' => $provider,
            'not_found_until' => now()->addDays(self::NOT_FOUND_DAYS),
            'last_used_at' => now(),
        ])->save();
    }

    /**
     * Posição confirmada (GPS preciso na entrega ou pino ajustado) passa a valer para os clientes da mesma
     * empresa com o mesmo endereço. Sem empresa definida, não é guardada.
     */
    public function rememberConfirmed(Customer $customer, float $latitude, float $longitude, string $source): void
    {
        if ($customer->user_id === null) {
            return;
        }

        $this->entryFor($customer, $customer->user_id)->fill([
            'latitude' => round($latitude, 7),
            'longitude' => round($longitude, 7),
            'location_type' => 'ROOFTOP',
            'source' => $source,
            'not_found_until' => null,
            'confirmed_at' => now(),
            'last_used_at' => now(),
        ])->save();
    }

    /**
     * @param  int|null  $tenantId  Nulo = entrada compartilhada do provedor
     */
    private function entryFor(Customer $customer, ?int $tenantId): GeocodedAddress
    {
        $address = $this->addressOf($customer);

        return GeocodedAddress::query()->firstOrNew(
            ['address_key' => self::keyFor($address), 'user_id' => $tenantId],
            [...$address, 'source' => GeocodedAddress::SOURCE_PROVIDER],
        );
    }

    /**
     * @return array{street: string, number: string|null, district: string|null, city: string, state: string, zip_code: string|null}
     */
    private function addressOf(Customer $customer): array
    {
        return [
            'street' => $customer->street,
            'number' => $customer->number,
            'district' => $customer->district,
            'city' => $customer->city,
            'state' => $customer->state,
            'zip_code' => $customer->zip_code,
        ];
    }

    private static function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9 ]+/', ' ')->squish()->toString();
    }
}
