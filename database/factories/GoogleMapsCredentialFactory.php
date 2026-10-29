<?php

namespace Database\Factories;

use App\Models\GoogleMapsCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleMapsCredential>
 */
class GoogleMapsCredentialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $apiKey = 'AIza'.fake()->regexify('[A-Za-z0-9_-]{35}');

        return [
            'user_id' => null,
            'api_key' => $apiKey,
            'key_hint' => mb_substr($apiKey, -4),
        ];
    }

    public function forTenant(int $userId): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $userId]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => now(),
            'last_checked_at' => now(),
        ]);
    }
}
