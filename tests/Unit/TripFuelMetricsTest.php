<?php

namespace Tests\Unit;

use App\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\Fuel;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripFuelMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fuel_efficiency_and_cost_per_km(): void
    {
        $trip = Trip::factory()->create([
            'km_start' => 0,
            'km_end' => 100,
            'km_total' => 100,
        ]);

        Fuel::factory()->create([
            'trip_id' => $trip->id,
            'liters' => 50,
            'price_per_liter' => 4,
        ]);

        $trip->refresh()->load(['fuel']);

        $this->assertSame(2.0, $trip->fuelEfficiencyKmPerLiter());
        $this->assertSame(2.0, $trip->fuelCostPerKm());
    }

    public function test_fuel_efficiency_returns_null_without_liters(): void
    {
        $trip = Trip::factory()->create([
            'km_total' => 100,
        ]);

        $trip->load('fuel');

        $this->assertNull($trip->fuelEfficiencyKmPerLiter());
    }

    public function test_expense_amount_for_returns_amount_for_each_type(): void
    {
        $trip = Trip::factory()->create();

        Expense::factory()->create([
            'trip_id' => $trip->id,
            'type' => ExpenseType::Toll,
            'amount' => 12.5,
        ]);
        Expense::factory()->create([
            'trip_id' => $trip->id,
            'type' => ExpenseType::Assistant,
            'amount' => 30,
        ]);

        $trip->refresh()->load('expenses');

        $this->assertSame(12.5, $trip->expenseAmountFor(ExpenseType::Toll));
        $this->assertSame(30.0, $trip->expenseAmountFor(ExpenseType::Assistant));
        $this->assertSame(0.0, $trip->expenseAmountFor(ExpenseType::Food));
    }
}
