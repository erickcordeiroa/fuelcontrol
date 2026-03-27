<?php

namespace App\Livewire\Reports;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\MetricsService;
use Carbon\Carbon;
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

        return view('livewire.reports.route-reports', [
            'metrics' => $metrics,
            'trips' => $trips,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
        ]);
    }
}
