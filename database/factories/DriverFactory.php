<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'license_number' => fake()->numerify('###########'),
            'phone' => fake()->optional(0.85)->phoneNumber(),
            'user_id' => null,
            'linked_user_id' => null,
        ];
    }

    public function forLinkedUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'linked_user_id' => $user->id,
        ]);
    }
}
