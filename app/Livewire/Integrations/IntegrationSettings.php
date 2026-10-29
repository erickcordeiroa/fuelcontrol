<?php

namespace App\Livewire\Integrations;

use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeCustomer;
use App\Models\Customer;
use App\Models\GeocodingRequest;
use App\Models\GoogleMapsCredential;
use App\Services\Geocoding\GeocoderFactory;
use App\Services\Geocoding\GeocodingCostEstimator;
use App\Services\Geocoding\GeocodingException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Integrações')]
class IntegrationSettings extends Component
{
    /** Endereço conhecido usado para testar a chave (gasta 1 consulta). */
    public const TEST_ADDRESS = 'Praça da Sé, São Paulo - SP';

    private const HISTORY_MONTHS = 6;

    public string $apiKey = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function save(GeocoderFactory $geocoders): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $validated = $this->validate([
            'apiKey' => ['required', 'string', 'between:20,100', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ], attributes: ['apiKey' => __('chave da API')]);

        GoogleMapsCredential::store($this->tenantId(), $validated['apiKey']);
        $this->reset('apiKey');

        $this->testKey($geocoders);
    }

    /**
     * Faz uma consulta real com a chave salva. Se funcionar, busca os clientes que ficaram sem localização.
     */
    public function testKey(GeocoderFactory $geocoders): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $credential = GoogleMapsCredential::forTenant($this->tenantId());

        if ($credential === null) {
            return;
        }

        try {
            $geocoders->withKey($credential->api_key, $this->tenantId())->geocode(self::TEST_ADDRESS);
        } catch (GeocodingException $e) {
            $credential->update(['verified_at' => null, 'last_error' => mb_substr($e->getMessage(), 0, 255), 'last_checked_at' => now()]);
            session()->flash('error', __('A chave não funcionou: :message', ['message' => $e->getMessage()]));

            return;
        }

        $credential->update(['verified_at' => now(), 'last_error' => null, 'last_checked_at' => now()]);
        $queued = $this->queuePendingCustomers();

        session()->flash('status', $queued > 0
            ? __('Chave funcionando. :count cliente(s) sem localização entraram na fila de busca.', ['count' => $queued])
            : __('Chave funcionando.'));
    }

    public function removeKey(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        GoogleMapsCredential::forTenant($this->tenantId())?->delete();

        session()->flash('status', __('Chave removida. A busca automática de endereços ficou desligada.'));
    }

    public function render(): View
    {
        $estimator = GeocodingCostEstimator::fromConfig();

        return view('livewire.integrations.integration-settings', [
            'credential' => GoogleMapsCredential::forTenant($this->tenantId()),
            'currentMonth' => $this->monthUsage(now(), $estimator, withProjection: true),
            'history' => $this->history($estimator),
            'freePerMonth' => $estimator->freePerMonth,
            'serverIp' => config('services.google_maps.server_ip'),
        ]);
    }

    /**
     * @return Collection<int, array{month: Carbon, billable: int, notBilled: int, cost: float, projected: int|null, projectedCost: float|null}>
     */
    private function history(GeocodingCostEstimator $estimator): Collection
    {
        return collect(range(1, self::HISTORY_MONTHS))
            ->map(fn (int $monthsAgo): array => $this->monthUsage(now()->startOfMonth()->subMonths($monthsAgo), $estimator));
    }

    /**
     * Consumo desta empresa no mês. Como cada empresa usa a própria conta no Google, a faixa grátis é só dela.
     *
     * @return array{month: Carbon, billable: int, notBilled: int, cost: float, projected: int|null, projectedCost: float|null}
     */
    private function monthUsage(Carbon $month, GeocodingCostEstimator $estimator, bool $withProjection = false): array
    {
        $counts = GeocodingRequest::query()
            ->inMonth($month)
            ->selectRaw('sum(case when billable then 1 else 0 end) as billable_count, sum(case when billable then 0 else 1 end) as other_count')
            ->first();

        $billable = (int) ($counts?->billable_count ?? 0);
        $projected = $withProjection ? $estimator->projectMonth($billable, now()) : null;

        return [
            'month' => $month->copy()->startOfMonth(),
            'billable' => $billable,
            'notBilled' => (int) ($counts?->other_count ?? 0),
            'cost' => $estimator->costFor($billable),
            'projected' => $projected,
            'projectedCost' => $projected === null ? null : $estimator->costFor($projected),
        ];
    }

    private function queuePendingCustomers(): int
    {
        $queued = 0;

        Customer::query()
            ->whereIn('geocode_status', [GeocodeStatus::Pending, GeocodeStatus::Failed])
            ->where(fn (Builder $query) => $query->whereNull('confirmed_latitude')->orWhereNull('confirmed_longitude'))
            ->where(fn (Builder $query) => $query->whereNull('latitude')->orWhereNull('longitude'))
            ->lazyById(500)
            ->each(function (Customer $customer) use (&$queued): void {
                $customer->forceFill(['geocode_status' => GeocodeStatus::Pending, 'geocode_error' => null])->save();
                GeocodeCustomer::dispatch($customer);
                $queued++;
            });

        return $queued;
    }

    private function tenantId(): int
    {
        return auth()->user()->tenantOwnerId();
    }
}
