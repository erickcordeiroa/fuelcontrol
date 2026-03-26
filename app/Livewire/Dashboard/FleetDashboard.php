<?php

namespace App\Livewire\Dashboard;

use App\Services\MetricsService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Painel')]
class FleetDashboard extends Component
{
    public string $startDate = '';

    public string $endDate = '';

    public function mount(): void
    {
        $start = CarbonImmutable::now()->startOfMonth();
        $end = CarbonImmutable::now()->endOfMonth();
        $this->startDate = $start->toDateString();
        $this->endDate = $end->toDateString();
    }

    public function render()
    {
        $user = auth()->user();
        $driverId = null;

        if (! $user->isAdmin()) {
            $driverId = $user->driver?->id;
        }

        $metrics = $driverId === null && ! $user->isAdmin()
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
                null,
                $driverId,
            );

        return view('livewire.dashboard.fleet-dashboard', [
            'metrics' => $metrics,
        ]);
    }
}
