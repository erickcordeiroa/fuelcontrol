<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Models\Delivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'customer_id' => Customer::factory(),
            'order_number' => fake()->unique()->bothify('PED-######'),
            'scheduled_for' => now()->toDateString(),
            'amount' => fake()->randomFloat(2, 30, 2500),
            'sector' => fake()->randomElement(['Sul', 'Norte', 'Leste']),
            'status' => DeliveryStatus::Pending,
        ];
    }

    public function forTenant(int $userId): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $userId]);
    }

    public function status(DeliveryStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'delivered_at' => $status === DeliveryStatus::Delivered ? now() : null,
        ]);
    }
}
