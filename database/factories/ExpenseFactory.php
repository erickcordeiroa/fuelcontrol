<?php

namespace Database\Factories;

use App\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'type' => fake()->randomElement(ExpenseType::cases()),
            'amount' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
