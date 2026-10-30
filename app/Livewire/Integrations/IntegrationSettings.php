<?php

namespace App\Livewire\Integrations;

use App\Models\Customer;
use App\Models\GeocodingRequest;
use App\Services\Geocoding\GeocoderFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Busca de endereços pela conta única da plataforma (spec 005): a empresa só acompanha o uso da cota.
 */
#[Layout('layouts.app')]
#[Title('Integrações')]
class IntegrationSettings extends Component
{
    private const HISTORY_MONTHS = 6;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function render(GeocoderFactory $geocoders): View
    {
        $quota = $geocoders->monthlyQuota(auth()->user()->tenantOwnerId());

        return view('livewire.integrations.integration-settings', [
            'serviceAvailable' => $geocoders->isConfigured(),
            'quota' => $quota,
            'currentMonth' => $this->monthUsage(now()),
            'history' => $this->history(),
            'reusedAddresses' => Customer::query()->where('geocode_provider', 'address_book')->count(),
        ]);
    }

    /**
     * @return Collection<int, array{month: Carbon, billable: int, notBilled: int}>
     */
    private function history(): Collection
    {
        return collect(range(1, self::HISTORY_MONTHS))
            ->map(fn (int $monthsAgo): array => $this->monthUsage(now()->startOfMonth()->subMonths($monthsAgo)));
    }

    /**
     * Consultas desta empresa no mês. Só as respondidas pelo serviço contam na cota.
     *
     * @return array{month: Carbon, billable: int, notBilled: int}
     */
    private function monthUsage(Carbon $month): array
    {
        $counts = GeocodingRequest::query()
            ->addressLookups()
            ->inMonth($month)
            ->selectRaw('sum(case when billable then 1 else 0 end) as billable_count, sum(case when billable then 0 else 1 end) as other_count')
            ->first();

        return [
            'month' => $month->copy()->startOfMonth(),
            'billable' => (int) ($counts?->billable_count ?? 0),
            'notBilled' => (int) ($counts?->other_count ?? 0),
        ];
    }
}
