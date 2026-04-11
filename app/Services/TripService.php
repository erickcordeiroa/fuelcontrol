<?php

namespace App\Services;

use App\Enums\ExpenseType;
use App\Enums\FuelType;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\GasStation;
use App\Models\Trip;
use App\Models\TripChangeLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\PricePerLiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripService
{
    /**
     * Preview KPIs for the logbook form without persisting.
     *
     * @param  array{km_start?: int|null, km_end?: int|null, liters?: float|string|null, price_per_liter?: float|string|null, revenue?: float|string|null, toll?: float|string|null, assistant?: float|string|null, food?: float|string|null}  $input
     * @return array{km_total: int|null, fuel_cost: float, total_expenses: float, net_margin: float|null, efficiency_km_per_liter: float|null, cost_per_km: float|null}
     */
    public function previewTripMetrics(array $input): array
    {
        $kmStart = isset($input['km_start']) ? (int) $input['km_start'] : null;
        $kmEnd = isset($input['km_end']) ? (int) $input['km_end'] : null;

        $kmTotal = null;
        if ($kmStart !== null && $kmEnd !== null && $kmEnd > $kmStart) {
            $kmTotal = $kmEnd - $kmStart;
        }

        $liters = (float) ($input['liters'] ?? 0);
        $pricePerLiter = PricePerLiter::normalize($input['price_per_liter'] ?? 0);
        $fuelCost = PricePerLiter::fuelCost($liters, $pricePerLiter);

        $toll = (float) ($input['toll'] ?? 0);
        $assistant = (float) ($input['assistant'] ?? 0);
        $food = (float) ($input['food'] ?? 0);
        $totalExpenses = $toll + $assistant + $food;

        $revenue = (float) ($input['revenue'] ?? 0);

        $netMargin = null;
        if ($kmTotal !== null) {
            $netMargin = $revenue - $fuelCost - $totalExpenses;
        }

        $efficiency = ($kmTotal !== null && $kmTotal > 0 && $liters > 0)
            ? round($kmTotal / $liters, 2)
            : null;

        $costPerKm = ($kmTotal !== null && $kmTotal > 0 && $fuelCost > 0)
            ? round($fuelCost / $kmTotal, 2)
            : null;

        return [
            'km_total' => $kmTotal,
            'fuel_cost' => round($fuelCost, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_margin' => $netMargin !== null ? round($netMargin, 2) : null,
            'efficiency_km_per_liter' => $efficiency,
            'cost_per_km' => $costPerKm,
        ];
    }

    /**
     * @param  array{
     *   date: string,
     *   vehicle_id: int,
     *   driver_id: int,
     *   km_start: int,
     *   km_end: int,
     *   revenue: float|int|string,
     *   liters: float|int|string,
     *   price_per_liter: float|int|string,
     *   station?: ?string,
     *   gas_station_id?: int|null,
     *   gas_station_fuel_offering_id?: int|null,
     *   fuel_type: FuelType|string,
     *   toll: float|int|string,
     *   assistant: float|int|string,
     *   food: float|int|string,
     *   trip_time?: ?string,
     *   notes?: ?string,
     *   status?: TripStatus
     * }  $payload
     */
    public function createTrip(User $user, array $payload): Trip
    {
        $driverId = (int) $payload['driver_id'];

        if ($user->role === UserRole::Driver) {
            if ($user->driver === null) {
                throw ValidationException::withMessages([
                    'driver_id' => [__('Your account is not linked to a driver profile.')],
                ]);
            }

            if ($user->driver->id !== $driverId) {
                throw ValidationException::withMessages([
                    'driver_id' => [__('You may only log trips for your own driver profile.')],
                ]);
            }
        }

        $kmStart = (int) $payload['km_start'];
        $kmEnd = (int) $payload['km_end'];

        if ($kmEnd <= $kmStart) {
            throw ValidationException::withMessages([
                'km_end' => [__('End KM must be greater than start KM.')],
            ]);
        }

        $kmTotal = $kmEnd - $kmStart;

        $status = $payload['status'] ?? TripStatus::Completed;

        Vehicle::query()->findOrFail((int) $payload['vehicle_id']);
        Driver::query()->findOrFail($driverId);

        if (isset($payload['gas_station_id']) && $payload['gas_station_id'] !== null) {
            GasStation::query()->findOrFail((int) $payload['gas_station_id']);
        }

        return DB::transaction(function () use ($payload, $kmTotal, $kmStart, $kmEnd, $driverId, $status) {
            $trip = Trip::query()->create([
                'date' => $payload['date'],
                'trip_time' => $payload['trip_time'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'vehicle_id' => (int) $payload['vehicle_id'],
                'driver_id' => $driverId,
                'km_start' => $kmStart,
                'km_end' => $kmEnd,
                'km_total' => $kmTotal,
                'revenue' => $payload['revenue'],
                'status' => $status,
            ]);

            $fuelType = $payload['fuel_type'] instanceof FuelType
                ? $payload['fuel_type']
                : FuelType::from((string) $payload['fuel_type']);

            $trip->fuel()->create([
                'gas_station_id' => $payload['gas_station_id'] ?? null,
                'gas_station_fuel_offering_id' => $payload['gas_station_fuel_offering_id'] ?? null,
                'fuel_type' => $fuelType,
                'liters' => $payload['liters'],
                'price_per_liter' => $payload['price_per_liter'],
                'station' => $payload['station'] ?? null,
            ]);

            $trip->expenses()->createMany([
                ['type' => ExpenseType::Toll, 'amount' => $payload['toll']],
                ['type' => ExpenseType::Assistant, 'amount' => $payload['assistant']],
                ['type' => ExpenseType::Food, 'amount' => $payload['food']],
            ]);

            return $trip->load(['fuel', 'expenses', 'vehicle', 'driver']);
        });
    }

    /**
     * Normalized trip + fuel + expenses for audit snapshots.
     *
     * @return array<string, mixed>
     */
    public function snapshotTripState(Trip $trip): array
    {
        $trip->loadMissing(['fuel', 'expenses']);
        $fuel = $trip->fuel;

        return [
            'date' => $trip->date->toDateString(),
            'trip_time' => $trip->trip_time,
            'notes' => $trip->notes,
            'vehicle_id' => $trip->vehicle_id,
            'driver_id' => $trip->driver_id,
            'km_start' => $trip->km_start,
            'km_end' => $trip->km_end,
            'revenue' => round((float) $trip->revenue, 2),
            'gas_station_id' => $fuel?->gas_station_id,
            'gas_station_fuel_offering_id' => $fuel?->gas_station_fuel_offering_id,
            'fuel_type' => $fuel?->fuel_type->value,
            'liters' => $fuel !== null ? round((float) $fuel->liters, 4) : null,
            'price_per_liter' => $fuel !== null ? round((float) $fuel->price_per_liter, 4) : null,
            'station' => $fuel?->station,
            'toll' => round($trip->expenseAmountFor(ExpenseType::Toll), 2),
            'assistant' => round($trip->expenseAmountFor(ExpenseType::Assistant), 2),
            'food' => round($trip->expenseAmountFor(ExpenseType::Food), 2),
        ];
    }

    /**
     * @param  array{
     *   date: string,
     *   vehicle_id: int,
     *   driver_id: int,
     *   km_start: int,
     *   km_end: int,
     *   revenue: float|int|string,
     *   liters: float|int|string,
     *   price_per_liter: float|int|string,
     *   station?: ?string,
     *   gas_station_id?: int|null,
     *   gas_station_fuel_offering_id?: int|null,
     *   fuel_type: FuelType|string,
     *   toll: float|int|string,
     *   assistant: float|int|string,
     *   food: float|int|string,
     *   trip_time?: ?string,
     *   notes?: ?string,
     *   status?: TripStatus
     * }  $payload
     */
    public function updateTrip(User $user, Trip $trip, array $payload): Trip
    {
        $driverId = (int) $payload['driver_id'];

        if ($user->role === UserRole::Driver) {
            if ($user->driver === null) {
                throw ValidationException::withMessages([
                    'driver_id' => [__('Your account is not linked to a driver profile.')],
                ]);
            }

            if ($user->driver->id !== $driverId) {
                throw ValidationException::withMessages([
                    'driver_id' => [__('You may only log trips for your own driver profile.')],
                ]);
            }
        }

        $kmStart = (int) $payload['km_start'];
        $kmEnd = (int) $payload['km_end'];

        if ($kmEnd <= $kmStart) {
            throw ValidationException::withMessages([
                'km_end' => [__('End KM must be greater than start KM.')],
            ]);
        }

        $kmTotal = $kmEnd - $kmStart;

        Vehicle::query()->findOrFail((int) $payload['vehicle_id']);
        Driver::query()->findOrFail($driverId);

        if (isset($payload['gas_station_id']) && $payload['gas_station_id'] !== null) {
            GasStation::query()->findOrFail((int) $payload['gas_station_id']);
        }

        $trip->loadMissing(['fuel', 'expenses']);

        if ($trip->fuel === null) {
            throw ValidationException::withMessages([
                'trip' => [__('This trip has no fuel record and cannot be edited from the logbook.')],
            ]);
        }

        $before = $this->snapshotTripState($trip);

        return DB::transaction(function () use ($user, $trip, $payload, $kmTotal, $kmStart, $kmEnd, $driverId, $before) {
            $trip->update([
                'date' => $payload['date'],
                'trip_time' => $payload['trip_time'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'vehicle_id' => (int) $payload['vehicle_id'],
                'driver_id' => $driverId,
                'km_start' => $kmStart,
                'km_end' => $kmEnd,
                'km_total' => $kmTotal,
            ]);

            $fuelType = $payload['fuel_type'] instanceof FuelType
                ? $payload['fuel_type']
                : FuelType::from((string) $payload['fuel_type']);

            $trip->fuel->update([
                'gas_station_id' => $payload['gas_station_id'] ?? null,
                'gas_station_fuel_offering_id' => $payload['gas_station_id'] ? ($payload['gas_station_fuel_offering_id'] ?? null) : null,
                'fuel_type' => $fuelType,
                'liters' => $payload['liters'],
                'price_per_liter' => $payload['price_per_liter'],
                'station' => $payload['station'] ?? null,
            ]);

            $this->syncTripExpenseAmount($trip, ExpenseType::Toll, (float) $payload['toll']);
            $this->syncTripExpenseAmount($trip, ExpenseType::Assistant, (float) $payload['assistant']);
            $this->syncTripExpenseAmount($trip, ExpenseType::Food, (float) $payload['food']);

            $trip->refresh();
            $trip->load(['fuel', 'expenses']);

            $after = $this->snapshotTripState($trip);

            $tempLog = new TripChangeLog([
                'before' => $before,
                'after' => $after,
            ]);

            if (count($tempLog->diffSnapshots()) > 0) {
                TripChangeLog::query()->create([
                    'trip_id' => $trip->id,
                    'user_id' => $user->id,
                    'before' => $before,
                    'after' => $after,
                ]);
            }

            return $trip->load(['fuel', 'expenses', 'vehicle', 'driver']);
        });
    }

    private function syncTripExpenseAmount(Trip $trip, ExpenseType $type, float $amount): void
    {
        $expense = $trip->expenses()->where('type', $type)->first();

        if ($expense === null) {
            $trip->expenses()->create([
                'type' => $type,
                'amount' => $amount,
            ]);

            return;
        }

        $expense->update(['amount' => $amount]);
    }
}
