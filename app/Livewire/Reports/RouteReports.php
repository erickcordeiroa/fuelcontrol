<?php

namespace App\Livewire\Reports;

use App\Enums\FuelType;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\TripChangeLog;
use App\Models\Vehicle;
use App\Services\MetricsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Relatórios de Rotas')]
class RouteReports extends Component
{
    use WithPagination;

    public string $startDate = '';

    public string $endDate = '';

    public string $startDateBr = '';

    public string $endDateBr = '';

    public ?int $filterVehicleId = null;

    public ?int $filterDriverId = null;

    public ?int $historyTripId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Trip::class);

        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
        $this->syncBrFromIso();
    }

    protected function syncBrFromIso(): void
    {
        $this->startDateBr = Carbon::parse($this->startDate)->format('d/m/Y');
        $this->endDateBr = Carbon::parse($this->endDate)->format('d/m/Y');
    }

    public function applyFilters(): void
    {
        $this->validate([
            'startDateBr' => ['required', 'date_format:d/m/Y'],
            'endDateBr' => ['required', 'date_format:d/m/Y'],
        ], [], [
            'startDateBr' => __('De'),
            'endDateBr' => __('Até'),
        ]);

        try {
            $start = Carbon::createFromFormat('d/m/Y', $this->startDateBr)->startOfDay();
            $end = Carbon::createFromFormat('d/m/Y', $this->endDateBr)->startOfDay();
        } catch (\Throwable) {
            $this->addError('startDateBr', __('Datas inválidas. Use dd/mm/aaaa.'));

            return;
        }

        if ($end->lt($start)) {
            $this->addError('endDateBr', __('A data final deve ser igual ou posterior à inicial.'));

            return;
        }

        $this->startDate = $start->toDateString();
        $this->endDate = $end->toDateString();
        $this->resetPage();
    }

    public function updatedStartDateBr(string $value): void
    {
        if (strlen($value) < 10) {
            return;
        }

        try {
            $this->startDate = Carbon::createFromFormat('d/m/Y', $value)->toDateString();
            $this->resetPage();
        } catch (\Throwable) {
            //
        }
    }

    public function updatedEndDateBr(string $value): void
    {
        if (strlen($value) < 10) {
            return;
        }

        try {
            $this->endDate = Carbon::createFromFormat('d/m/Y', $value)->toDateString();
            $this->resetPage();
        } catch (\Throwable) {
            //
        }
    }

    public function updating($name): void
    {
        if (str_starts_with((string) $name, 'filter') || str_ends_with((string) $name, 'DateBr')) {
            $this->resetPage();
        }
    }

    public function openTripHistory(int $tripId): void
    {
        $trip = Trip::query()->findOrFail($tripId);
        Gate::authorize('view', $trip);
        $this->historyTripId = $tripId;
    }

    public function closeTripHistory(): void
    {
        $this->historyTripId = null;
    }

    public function formatSnapshotScalar(string $field, mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if ($field === 'fuel_type' && is_string($value)) {
            $enum = FuelType::tryFrom($value);

            return $enum !== null ? $enum->label() : $value;
        }

        if (is_float($value) || is_int($value)) {
            if (in_array($field, ['liters', 'price_per_liter'], true)) {
                return number_format((float) $value, 2, ',', '.');
            }

            if (in_array($field, ['toll', 'assistant', 'food', 'revenue'], true)) {
                return 'R$ '.number_format((float) $value, 2, ',', '.');
            }
        }

        if (is_string($value) && $field === 'date') {
            try {
                return Carbon::parse($value)->format('d/m/Y');
            } catch (\Throwable) {
                return $value;
            }
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }

    /**
     * @param  Collection<int, string>|iterable<int|string, string>  $vehiclePlates
     * @param  Collection<int, string>|iterable<int|string, string>  $driverNames
     */
    public function formatSnapshotValue(string $field, mixed $value, iterable $vehiclePlates, iterable $driverNames): string
    {
        if ($value === null) {
            return '—';
        }

        if ($field === 'vehicle_id' && is_numeric($value)) {
            $id = (int) $value;
            $plate = $vehiclePlates[$id] ?? null;

            return $plate !== null ? (string) $plate : '#'.$id;
        }

        if ($field === 'driver_id' && is_numeric($value)) {
            $id = (int) $value;
            $name = $driverNames[$id] ?? null;

            return $name !== null ? (string) $name : '#'.$id;
        }

        return $this->formatSnapshotScalar($field, $value);
    }

    public function render()
    {
        $user = auth()->user();
        $driverScopeId = $user->isAdmin() ? null : $user->driver?->id;

        $vehicleId = $this->filterVehicleId;
        $driverId = $user->isAdmin() ? $this->filterDriverId : $driverScopeId;

        $metrics = $driverScopeId === null && ! $user->isAdmin()
            ? [
                'total_fuel_cost' => 0.0,
                'total_other_expenses' => 0.0,
                'total_operational_cost' => 0.0,
                'total_km' => 0,
                'total_liters' => 0.0,
                'efficiency_km_per_liter' => null,
                'cost_per_km' => null,
            ]
            : app(MetricsService::class)->getAggregates(
                $this->startDate,
                $this->endDate,
                $vehicleId,
                $driverId,
            );

        $series = app(MetricsService::class)->getDailySeries(
            $this->startDate,
            $this->endDate,
            $vehicleId,
            $driverId,
        );

        $vehicleRows = app(MetricsService::class)->getVehicleEfficiencyRows(
            $this->startDate,
            $this->endDate,
            $driverId,
        );

        $this->js(sprintf(
            'window.fleetCharts.line("reportLine", %s, %s, %s); window.fleetCharts.horizontalBars("reportBars", %s);',
            json_encode($series['labels']),
            json_encode($series['fuel_cost']),
            json_encode($series['other_expenses']),
            json_encode($vehicleRows)
        ));

        $trips = Trip::query()
            ->with(['vehicle', 'driver', 'fuel', 'expenses'])
            ->whereDate('date', '>=', $this->startDate)
            ->whereDate('date', '<=', $this->endDate)
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->when($user->isAdmin() && $this->filterDriverId, fn ($q) => $q->where('driver_id', $this->filterDriverId))
            ->when(! $user->isAdmin(), function ($q) use ($driverScopeId) {
                if ($driverScopeId === null) {
                    $q->whereRaw('1 = 0');
                } else {
                    $q->where('driver_id', $driverScopeId);
                }
            })
            ->orderByDesc('date')
            ->paginate(10);

        $vehicles = $user->isAdmin()
            ? Vehicle::query()->orderBy('plate')->get()
            : collect();

        $drivers = $user->isAdmin()
            ? Driver::query()->orderBy('name')->get()
            : collect();

        $tripChangeLogs = $this->historyTripId === null
            ? collect()
            : TripChangeLog::query()
                ->where('trip_id', $this->historyTripId)
                ->with('user')
                ->latest()
                ->get();

        $vehiclePlates = Vehicle::query()->orderBy('plate')->pluck('plate', 'id');
        $driverNames = Driver::query()->orderBy('name')->pluck('name', 'id');

        return view('livewire.reports.route-reports', [
            'metrics' => $metrics,
            'trips' => $trips,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'tripChangeLogs' => $tripChangeLogs,
            'vehiclePlates' => $vehiclePlates,
            'driverNames' => $driverNames,
        ]);
    }
}
