<?php

namespace App\Livewire\GasStations;

use App\Models\GasStation;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Postos')]
class GasStationIndex extends Component
{
    use WithPagination;

    public function delete(int $id): void
    {
        $gasStation = GasStation::query()->findOrFail($id);
        Gate::authorize('delete', $gasStation);
        $gasStation->delete();
        session()->flash('status', __('Posto removido.'));
    }

    public function render()
    {
        Gate::authorize('viewAny', GasStation::class);

        return view('livewire.gas-stations.gas-station-index', [
            'gasStations' => GasStation::query()->orderBy('name')->paginate(10),
        ]);
    }
}
