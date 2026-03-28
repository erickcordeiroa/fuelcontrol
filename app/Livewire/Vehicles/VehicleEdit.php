<?php

namespace App\Livewire\Vehicles;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Editar veículo')]
class VehicleEdit extends Component
{
    public Vehicle $vehicle;

    public string $plate = '';

    public string $model = '';

    public string $capacity = '';

    public string $fuel_type = '';

    public function mount(Vehicle $vehicle): void
    {
        Gate::authorize('update', $vehicle);

        $this->vehicle = $vehicle;
        $this->plate = $vehicle->plate;
        $this->model = $vehicle->model;
        $this->capacity = (string) $vehicle->capacity;
        $this->fuel_type = $vehicle->fuel_type;
    }

    public function save(): void
    {
        Gate::authorize('update', $this->vehicle);

        $tenantId = auth()->user()->tenantOwnerId();

        $validated = $this->validate([
            'plate' => [
                'required',
                'string',
                'max:32',
                'regex:/^([A-Za-z]{3}-[0-9]{4}|[A-Za-z]{3}-[0-9][A-Za-z][0-9]{2})$/',
                Rule::unique('vehicles', 'plate')
                    ->where(fn ($q) => $q->where('user_id', $tenantId))
                    ->ignore($this->vehicle->id),
            ],
            'model' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'fuel_type' => ['required', 'string', 'max:64'],
        ]);

        $this->vehicle->update([
            'plate' => strtoupper($validated['plate']),
            'model' => $validated['model'],
            'capacity' => (int) $validated['capacity'],
            'fuel_type' => $validated['fuel_type'],
        ]);

        session()->flash('status', __('Veículo atualizado.'));
        $this->redirect(route('vehicles.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.vehicles.vehicle-edit');
    }
}
