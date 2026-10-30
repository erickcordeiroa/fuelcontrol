<?php

namespace App\Livewire\Reports;

use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Services\Billing\PlanLimits;
use App\Services\Deliveries\DeliveryReport;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Relatório de entregas e custo por entrega')]
class DeliveryReports extends Component
{
    #[Url(as: 'de')]
    public string $startDate = '';

    #[Url(as: 'ate')]
    public string $endDate = '';

    #[Url(as: 'motorista')]
    public ?int $filterDriverId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', DeliveryRoute::class);

        if (! $this->isValidDate($this->startDate) || ! $this->isValidDate($this->endDate)) {
            $this->startDate = now()->startOfMonth()->toDateString();
            $this->endDate = now()->toDateString();
        }
    }

    public function applyFilters(): void
    {
        $this->validate([
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ], [
            'endDate.after_or_equal' => __('A data final deve ser igual ou posterior à inicial.'),
        ], [
            'startDate' => __('De'),
            'endDate' => __('Até'),
        ]);
    }

    public function render(DeliveryReport $report, PlanLimits $planLimits): View
    {
        $exportQuery = array_filter([
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'driver_id' => $this->filterDriverId,
        ], fn ($value): bool => $value !== null && $value !== '');

        return view('livewire.reports.delivery-reports', [
            'report' => $report->build($this->startDate, $this->endDate, $this->filterDriverId),
            'drivers' => Driver::query()->orderBy('name')->get(['id', 'name']),
            'canExport' => $planLimits->allows(auth()->user()->tenantOwnerId(), 'report_export'),
            'exportCsvUrl' => route('reports.deliveries.export', $exportQuery),
        ]);
    }

    private function isValidDate(string $value): bool
    {
        return $value !== '' && Carbon::canBeCreatedFromFormat($value, 'Y-m-d');
    }
}
