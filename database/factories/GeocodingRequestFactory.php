<?php

namespace Database\Factories;

use App\Models\GeocodingRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeocodingRequest>
 */
class GeocodingRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'customer_id' => null,
            'lookup' => GeocodingRequest::LOOKUP_ADDRESS,
            'provider_status' => 'OK',
            'billable' => true,
        ];
    }

    public function forTenant(int $userId): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $userId]);
    }

    public function notBillable(string $status = 'OVER_QUERY_LIMIT'): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_status' => $status,
            'billable' => false,
        ]);
    }
}
