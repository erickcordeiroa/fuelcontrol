<?php

namespace Database\Factories;

use App\Enums\GeocodeStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $address = [
            'street' => fake()->randomElement(['Avenida Clara Gianotti de Souza', 'Rua Shitiro Maeji', 'Avenida Wild José de Souza', 'Rua Capitão João Pocci']),
            'number' => (string) fake()->numberBetween(10, 3000),
            'district' => fake()->randomElement(['Centro', 'Vila Tupi', 'Vila Ribeirópolis', 'Jardim São Paulo']),
            'city' => 'Registro',
            'state' => 'SP',
            'zip_code' => '11900000',
        ];

        return [
            'user_id' => null,
            'external_code' => fake()->unique()->bothify('C-#####'),
            'name' => fake()->company(),
            ...$address,
            'reference' => fake()->optional()->sentence(4),
            'address_hash' => Customer::hashAddress($address),
            'geocode_status' => GeocodeStatus::Pending,
        ];
    }

    /**
     * Coordenada vinda do cache do Google (expira em 30 dias).
     */
    public function located(?float $latitude = null, ?float $longitude = null): static
    {
        return $this->state(fn (array $attributes) => [
            'geocode_status' => GeocodeStatus::Resolved,
            'google_place_id' => 'ChIJ'.fake()->regexify('[A-Za-z0-9]{20}'),
            'latitude' => $latitude ?? fake()->latitude(-24.53, -24.48),
            'longitude' => $longitude ?? fake()->longitude(-47.88, -47.81),
            'location_type' => 'ROOFTOP',
            'geocoded_at' => now(),
        ]);
    }

    /**
     * Coordenada própria (GPS do motorista), permanente.
     */
    public function confirmed(?float $latitude = null, ?float $longitude = null): static
    {
        return $this->state(fn (array $attributes) => [
            'geocode_status' => GeocodeStatus::Confirmed,
            'confirmed_latitude' => $latitude ?? fake()->latitude(-24.53, -24.48),
            'confirmed_longitude' => $longitude ?? fake()->longitude(-47.88, -47.81),
            'confirmed_at' => now(),
        ]);
    }
}
