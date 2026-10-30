<?php

namespace Database\Factories;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'plan' => 'inicial',
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ];
    }

    public function forTenant(int $userId): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $userId]);
    }

    public function plan(string $plan): static
    {
        return $this->state(fn (array $attributes) => ['plan' => $plan]);
    }
}
