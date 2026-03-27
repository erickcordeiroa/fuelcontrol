<?php

namespace App\Livewire\GasStations;

use App\Models\GasStation;
use App\Support\BrazilianNumber;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Editar posto')]
class GasStationEdit extends Component
{
    public GasStation $gasStation;

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $price_per_liter = '0,0000';

    public function mount(GasStation $gas_station): void
    {
        Gate::authorize('update', $gas_station);

        $this->gasStation = $gas_station;
        $this->name = $gas_station->name;
        $this->phone = (string) ($gas_station->phone ?? '');
        $this->address = (string) ($gas_station->address ?? '');
        $this->price_per_liter = BrazilianNumber::format((float) $gas_station->price_per_liter, 4);
    }

    public function save(): void
    {
        Gate::authorize('update', $this->gasStation);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
            'price_per_liter' => ['required', 'string'],
        ]);

        $price = BrazilianNumber::parse($validated['price_per_liter']);

        if ($price < 0) {
            $this->addError('price_per_liter', __('O valor do litro deve ser zero ou positivo.'));

            return;
        }

        $this->gasStation->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
            'address' => $validated['address'] !== '' ? $validated['address'] : null,
            'price_per_liter' => round($price, 4),
        ]);

        session()->flash('status', __('Posto atualizado.'));
        $this->redirect(route('gas-stations.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.gas-stations.gas-station-edit');
    }
}
