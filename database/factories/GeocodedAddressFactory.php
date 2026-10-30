<?php

namespace Database\Factories;

use App\Models\GeocodedAddress;
use App\Services\Geocoding\AddressBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeocodedAddress>
 */
class GeocodedAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $address = [
            'street' => 'Rua Shitiro Maeji',
            'number' => (string) fake()->unique()->numberBetween(1, 9999),
            'district' => 'Centro',
            'city' => 'Registro',
            'state' => 'SP',
            'zip_code' => '11900000',
        ];

        return [
            ...$address,
            'address_key' => AddressBook::keyFor($address),
            'latitude' => fake()->latitude(-24.53, -24.48),
            'longitude' => fake()->longitude(-47.88, -47.81),
            'location_type' => 'ROOFTOP',
            'source' => GeocodedAddress::SOURCE_PROVIDER,
            'provider' => 'geoapify',
        ];
    }
}
