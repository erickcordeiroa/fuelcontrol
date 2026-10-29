<?php

namespace App\Livewire\Deliveries;

use App\Models\DeliveryRoute;
use App\Models\User;
use App\Services\Geocoding\GeocoderFactory;
use App\Services\Geocoding\GeocodingException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Centro de distribuição da empresa: de onde as rotas saem e para onde voltam. Sem ele, a rota começa na
 * primeira parada e não traça a volta.
 */
class DepotSettings extends Component
{
    public bool $editing = false;

    public string $address = '';

    public string $latitude = '';

    public string $longitude = '';

    public function mount(): void
    {
        Gate::authorize('create', DeliveryRoute::class);

        $this->fillFromCompany();
    }

    public function edit(): void
    {
        $this->fillFromCompany();
        $this->resetErrorBag();
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->fillFromCompany();
        $this->resetErrorBag();
        $this->editing = false;
    }

    /**
     * Localiza o endereço digitado (conta 1 busca na cota da empresa). As coordenadas só valem depois de salvar.
     */
    public function locate(GeocoderFactory $geocoders): void
    {
        Gate::authorize('create', DeliveryRoute::class);

        $this->validate(['address' => ['required', 'string', 'min:8', 'max:255']], attributes: ['address' => __('endereço')]);

        $tenantId = auth()->user()->tenantOwnerId();

        if (! $geocoders->isConfigured() || ! $geocoders->hasQuotaFor($tenantId) || ! $geocoders->hasDailyCapacity()) {
            $this->addError('address', __('A busca de endereços não está disponível agora. Informe a latitude e a longitude (copie do Google Maps).'));

            return;
        }

        try {
            $result = $geocoders->forTenant($tenantId)->geocode($this->address);
        } catch (GeocodingException $e) {
            $this->addError('address', $e->getMessage());

            return;
        }

        if ($result === null) {
            $this->addError('address', __('Endereço não encontrado. Confira o texto ou informe a latitude e a longitude.'));

            return;
        }

        $this->latitude = (string) round($result->latitude, 7);
        $this->longitude = (string) round($result->longitude, 7);

        if ($result->formattedAddress !== null) {
            $this->address = $result->formattedAddress;
        }
    }

    public function save(): void
    {
        Gate::authorize('create', DeliveryRoute::class);

        $this->latitude = str_replace(',', '.', trim($this->latitude));
        $this->longitude = str_replace(',', '.', trim($this->longitude));

        $validated = $this->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ], attributes: [
            'address' => __('endereço'),
            'latitude' => __('latitude'),
            'longitude' => __('longitude'),
        ]);

        $this->company()->update([
            'depot_address' => $validated['address'] !== '' ? $validated['address'] : null,
            'depot_latitude' => (float) $validated['latitude'],
            'depot_longitude' => (float) $validated['longitude'],
        ]);

        $this->editing = false;
        $this->dispatch('depot-updated');
    }

    public function render(): View
    {
        $company = $this->company();

        return view('livewire.deliveries.depot-settings', [
            'depot' => $company->depot(),
            'depotAddress' => $company->depot_address,
        ]);
    }

    private function company(): User
    {
        return User::query()->findOrFail(auth()->user()->tenantOwnerId());
    }

    private function fillFromCompany(): void
    {
        $company = $this->company();

        $this->address = (string) $company->depot_address;
        $this->latitude = $company->depot_latitude === null ? '' : (string) $company->depot_latitude;
        $this->longitude = $company->depot_longitude === null ? '' : (string) $company->depot_longitude;
    }
}
