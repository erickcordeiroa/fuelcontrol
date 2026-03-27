<?php

namespace App\Livewire\GasStations;

use App\Models\GasStation;
use App\Support\BrazilianNumber;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Novo posto')]
class GasStationCreate extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $price_per_liter = '0,0000';

    public function save(): void
    {
        Gate::authorize('create', GasStation::class);

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

        GasStation::query()->create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
            'address' => $validated['address'] !== '' ? $validated['address'] : null,
            'price_per_liter' => round($price, 4),
        ]);

        session()->flash('status', __('Posto criado.'));
        $this->redirect(route('gas-stations.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.gas-stations.gas-station-create');
    }
}
