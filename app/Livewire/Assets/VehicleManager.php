<?php

namespace App\Livewire\Assets;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Veículos')]
class VehicleManager extends Component
{
    use WithPagination;

    public string $plate = '';

    public string $model = '';

    public string $capacity = '';

    public string $fuel_type = '';

    public ?int $editingId = null;

    public function startEdit(int $id): void
    {
        $vehicle = Vehicle::query()->findOrFail($id);
        Gate::authorize('update', $vehicle);

        $this->editingId = $vehicle->id;
        $this->plate = $vehicle->plate;
        $this->model = $vehicle->model;
        $this->capacity = (string) $vehicle->capacity;
        $this->fuel_type = $vehicle->fuel_type;
    }

    public function save(): void
    {
        $rules = [
            'plate' => ['required', 'string', 'max:32'],
            'model' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'fuel_type' => ['required', 'string', 'max:64'],
        ];

        if ($this->editingId === null) {
            $rules['plate'][] = 'unique:vehicles,plate';
        } else {
            $rules['plate'][] = 'unique:vehicles,plate,'.$this->editingId;
        }

        $validated = $this->validate($rules);

        if ($this->editingId === null) {
            Gate::authorize('create', Vehicle::class);
            Vehicle::query()->create([
                'plate' => strtoupper($validated['plate']),
                'model' => $validated['model'],
                'capacity' => (int) $validated['capacity'],
                'fuel_type' => $validated['fuel_type'],
            ]);
            session()->flash('status', __('Veículo criado.'));
        } else {
            $vehicle = Vehicle::query()->findOrFail($this->editingId);
            Gate::authorize('update', $vehicle);
            $vehicle->update([
                'plate' => strtoupper($validated['plate']),
                'model' => $validated['model'],
                'capacity' => (int) $validated['capacity'],
                'fuel_type' => $validated['fuel_type'],
            ]);
            session()->flash('status', __('Veículo atualizado.'));
        }

        $this->resetForm();
        $this->editingId = null;
    }

    public function delete(int $id): void
    {
        $vehicle = Vehicle::query()->findOrFail($id);
        Gate::authorize('delete', $vehicle);
        $vehicle->delete();
        session()->flash('status', __('Veículo removido.'));
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->editingId = null;
    }

    protected function resetForm(): void
    {
        $this->plate = '';
        $this->model = '';
        $this->capacity = '';
        $this->fuel_type = '';
    }

    public function render()
    {
        $vehicles = Vehicle::query()->orderBy('plate')->paginate(10);

        return view('livewire.assets.vehicle-manager', [
            'vehicles' => $vehicles,
        ]);
    }
}
