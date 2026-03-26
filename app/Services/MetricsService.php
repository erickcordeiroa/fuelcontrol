<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MetricsService
{
    /**
     * @return Builder<Trip>
     */
    public function tripsQuery(string $startDate, string $endDate, ?int $vehicleId = null, ?int $driverId = null): Builder
    {
        $query = Trip::query()
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate);

        if ($vehicleId !== null) {
            $query->where('vehicle_id', $vehicleId);
        }

        if ($driverId !== null) {
            $query->where('driver_id', $driverId);
        }

        return $query;
    }

    /**
     * @return array{
     *   total_fuel_cost: float,
     *   total_other_expenses: float,
     *   total_operational_cost: float,
     *   total_km: int,
     *   total_liters: float,
     *   efficiency_km_per_liter: float|null,
     *   cost_per_km: float|null
     * }
     *
     * efficiency_km_per_liter and cost_per_km are arithmetic means across each logbook entry (trip)
     * that has valid fuel data, not fleet-wide totals blended into one ratio.
     */
    public function getAggregates(string $startDate, string $endDate, ?int $vehicleId = null, ?int $driverId = null): array
    {
        $cacheKey = implode(':', [
            'fleet_metrics',
            $startDate,
            $endDate,
            (string) ($vehicleId ?? 'all'),
            (string) ($driverId ?? 'all'),
        ]);

        /** @var array<string, float|int|null> $data */
        $data = Cache::remember($cacheKey, 60, function () use ($startDate, $endDate, $vehicleId, $driverId) {
            $tripIds = $this->tripsQuery($startDate, $endDate, $vehicleId, $driverId)->pluck('id');

            if ($tripIds->isEmpty()) {
                return [
                    'total_fuel_cost' => 0.0,
                    'total_other_expenses' => 0.0,
                    'total_operational_cost' => 0.0,
                    'total_km' => 0,
                    'total_liters' => 0.0,
                    'efficiency_km_per_liter' => null,
                    'cost_per_km' => null,
                ];
            }

            $totalKm = (int) Trip::query()
                ->whereIn('id', $tripIds)
                ->sum('km_total');

            $fuelRow = DB::table('fuels')
                ->whereIn('trip_id', $tripIds)
                ->selectRaw('COALESCE(SUM(liters * price_per_liter), 0) as fuel_cost, COALESCE(SUM(liters), 0) as liters')
                ->first();

            $totalFuelCost = (float) ($fuelRow->fuel_cost ?? 0);
            $totalLiters = (float) ($fuelRow->liters ?? 0);

            $totalOtherExpenses = (float) DB::table('expenses')
                ->whereIn('trip_id', $tripIds)
                ->sum('amount');

            $totalOperational = $totalFuelCost + $totalOtherExpenses;

            $trips = Trip::query()
                ->whereIn('id', $tripIds)
                ->with(['fuel'])
                ->get();

            $kmPerLiterPerLogbook = [];
            $costPerKmPerLogbook = [];
            foreach ($trips as $trip) {
                $kmL = $trip->fuelEfficiencyKmPerLiter();
                if ($kmL !== null) {
                    $kmPerLiterPerLogbook[] = $kmL;
                }
                $cpk = $trip->fuelCostPerKm();
                if ($cpk !== null) {
                    $costPerKmPerLogbook[] = $cpk;
                }
            }

            $efficiency = count($kmPerLiterPerLogbook) > 0
                ? round(array_sum($kmPerLiterPerLogbook) / count($kmPerLiterPerLogbook), 2)
                : null;

            $costPerKm = count($costPerKmPerLogbook) > 0
                ? round(array_sum($costPerKmPerLogbook) / count($costPerKmPerLogbook), 2)
                : null;

            return [
                'total_fuel_cost' => round($totalFuelCost, 2),
                'total_other_expenses' => round($totalOtherExpenses, 2),
                'total_operational_cost' => round($totalOperational, 2),
                'total_km' => $totalKm,
                'total_liters' => round($totalLiters, 2),
                'efficiency_km_per_liter' => $efficiency,
                'cost_per_km' => $costPerKm,
            ];
        });

        return [
            'total_fuel_cost' => (float) $data['total_fuel_cost'],
            'total_other_expenses' => (float) $data['total_other_expenses'],
            'total_operational_cost' => (float) $data['total_operational_cost'],
            'total_km' => (int) $data['total_km'],
            'total_liters' => (float) $data['total_liters'],
            'efficiency_km_per_liter' => $data['efficiency_km_per_liter'] !== null ? (float) $data['efficiency_km_per_liter'] : null,
            'cost_per_km' => $data['cost_per_km'] !== null ? (float) $data['cost_per_km'] : null,
        ];
    }

    /**
     * Daily fuel cost and other expenses for charts.
     *
     * @return array{labels: list<string>, fuel_cost: list<float>, other_expenses: list<float>}
     */
    public function getDailySeries(string $startDate, string $endDate, ?int $vehicleId = null, ?int $driverId = null): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        $trips = $this->tripsQuery($startDate, $endDate, $vehicleId, $driverId)
            ->with(['fuel', 'expenses'])
            ->orderBy('date')
            ->get();

        $labels = [];
        $fuelCost = [];
        $otherExpenses = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d/m');

            $dayTrips = $trips->filter(fn (Trip $t) => $t->date->format('Y-m-d') === $key);

            $fuelCost[] = round((float) $dayTrips->sum(function (Trip $trip) {
                if ($trip->fuel === null) {
                    return 0;
                }

                return (float) $trip->fuel->liters * (float) $trip->fuel->price_per_liter;
            }), 2);

            $otherExpenses[] = round((float) $dayTrips->sum(fn (Trip $trip) => (float) $trip->expenses->sum('amount')), 2);
        }

        return [
            'labels' => $labels,
            'fuel_cost' => $fuelCost,
            'other_expenses' => $otherExpenses,
        ];
    }

    /**
     * Average km/L per vehicle in the period (fuel efficiency).
     *
     * @return list<array{vehicle_id: int, label: string, km_per_liter: float}>
     */
    public function getVehicleEfficiencyRows(string $startDate, string $endDate, ?int $driverId = null): array
    {
        $tripIds = $this->tripsQuery($startDate, $endDate, null, $driverId)->pluck('id');

        if ($tripIds->isEmpty()) {
            return [];
        }

        $kmByVehicle = Trip::query()
            ->whereIn('id', $tripIds)
            ->select('vehicle_id')
            ->selectRaw('SUM(km_total) as km_sum')
            ->groupBy('vehicle_id')
            ->pluck('km_sum', 'vehicle_id');

        $litersByVehicle = DB::table('trips')
            ->join('fuels', 'fuels.trip_id', '=', 'trips.id')
            ->whereIn('trips.id', $tripIds)
            ->select('trips.vehicle_id')
            ->selectRaw('SUM(fuels.liters) as liters_sum')
            ->groupBy('trips.vehicle_id')
            ->pluck('liters_sum', 'vehicle_id');

        $vehicles = Vehicle::query()->whereIn('id', $kmByVehicle->keys())->get()->keyBy('id');

        $result = [];
        foreach ($kmByVehicle as $vehicleId => $kmSum) {
            $vid = (int) $vehicleId;
            $km = (float) $kmSum;
            $liters = (float) ($litersByVehicle[$vid] ?? 0);
            $kmPerLiter = $liters > 0 ? round($km / $liters, 2) : 0.0;
            $vehicle = $vehicles->get($vid);

            $result[] = [
                'vehicle_id' => $vid,
                'label' => $vehicle ? $vehicle->plate.' · '.$vehicle->model : (string) $vid,
                'km_per_liter' => $kmPerLiter,
            ];
        }

        usort($result, fn (array $a, array $b) => $b['km_per_liter'] <=> $a['km_per_liter']);

        return $result;
    }
}
