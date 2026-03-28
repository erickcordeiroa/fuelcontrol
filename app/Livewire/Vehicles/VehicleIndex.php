<?php

namespace App\Livewire\Vehicles;

use App\Livewire\Concerns\ConfirmsDeletes;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Veículos')]
class VehicleIndex extends Component
{
    use ConfirmsDeletes;
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $plate = '';

    public string $model = '';

    public string $capacity = '';

    public string $fuel_type = '';

    public function openCreateModal(): void
    {
        Gate::authorize('create', Vehicle::class);

        $this->editingId = null;
        $this->resetForm();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $vehicle = Vehicle::query()->findOrFail($id);
        Gate::authorize('update', $vehicle);

        $this->editingId = $vehicle->id;
        $this->plate = $vehicle->plate;
        $this->model = $vehicle->model;
        $this->capacity = (string) $vehicle->capacity;
        $this->fuel_type = $vehicle->fuel_type;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->resetForm();
        $this->resetValidation();
    }

    private function resetForm(): void
    {
        $this->plate = '';
        $this->model = '';
        $this->capacity = '';
        $this->fuel_type = '';
    }

    public function save(): void
    {
        $tenantId = auth()->user()->tenantOwnerId();

        $plateRules = [
            'required',
            'string',
            'max:32',
            'regex:/^([A-Za-z]{3}-[0-9]{4}|[A-Za-z]{3}-[0-9][A-Za-z][0-9]{2})$/',
            Rule::unique('vehicles', 'plate')->where(fn ($q) => $q->where('user_id', $tenantId)),
        ];

        if ($this->editingId !== null) {
            $vehicle = Vehicle::query()->findOrFail($this->editingId);
            Gate::authorize('update', $vehicle);

            $plateRules = [
                'required',
                'string',
                'max:32',
                'regex:/^([A-Za-z]{3}-[0-9]{4}|[A-Za-z]{3}-[0-9][A-Za-z][0-9]{2})$/',
                Rule::unique('vehicles', 'plate')
                    ->where(fn ($q) => $q->where('user_id', $tenantId))
                    ->ignore($vehicle->id),
            ];

            $validated = $this->validate([
                'plate' => $plateRules,
                'model' => ['required', 'string', 'max:255'],
                'capacity' => ['required', 'integer', 'min:1'],
                'fuel_type' => ['required', 'string', 'max:64'],
            ]);

            $vehicle->update([
                'plate' => strtoupper($validated['plate']),
                'model' => $validated['model'],
                'capacity' => (int) $validated['capacity'],
                'fuel_type' => $validated['fuel_type'],
            ]);

            session()->flash('status', __('Veículo atualizado.'));
        } else {
            Gate::authorize('create', Vehicle::class);

            $validated = $this->validate([
                'plate' => $plateRules,
                'model' => ['required', 'string', 'max:255'],
                'capacity' => ['required', 'integer', 'min:1'],
                'fuel_type' => ['required', 'string', 'max:64'],
            ]);

            Vehicle::query()->create([
                'plate' => strtoupper($validated['plate']),
                'model' => $validated['model'],
                'capacity' => (int) $validated['capacity'],
                'fuel_type' => $validated['fuel_type'],
            ]);

            session()->flash('status', __('Veículo criado.'));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $vehicle = Vehicle::query()->findOrFail($id);
        Gate::authorize('delete', $vehicle);
        $vehicle->delete();

        if ($this->showModal && $this->editingId === $id) {
            $this->closeModal();
        }

        session()->flash('status', __('Veículo removido.'));
    }

    public function render()
    {
        Gate::authorize('viewAny', Vehicle::class);

        return view('livewire.vehicles.vehicle-index', [
            'vehicles' => Vehicle::query()->orderBy('plate')->paginate(10),
        ]);
    }
}
