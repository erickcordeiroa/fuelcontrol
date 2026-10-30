<?php

namespace Database\Factories;

use App\Models\RouteTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteTemplate>
 */
class RouteTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Setor '.fake()->unique()->word();

        return [
            'user_id' => User::factory()->admin(),
            'name' => $name,
            'name_key' => RouteTemplate::keyFor($name),
            'customer_ids' => [],
            'optimized_at' => now(),
        ];
    }
}
