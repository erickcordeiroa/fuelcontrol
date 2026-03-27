<?php

namespace App\Livewire\Vehicles;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Veículos')]
class VehicleIndex extends Component
{
    use WithPagination;

    public function delete(int $id): void
    {
        $vehicle = Vehicle::query()->findOrFail($id);
        Gate::authorize('delete', $vehicle);
        $vehicle->delete();
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
