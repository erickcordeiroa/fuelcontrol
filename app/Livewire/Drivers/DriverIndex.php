<?php

namespace App\Livewire\Drivers;

use App\Models\Driver;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Motoristas')]
class DriverIndex extends Component
{
    use WithPagination;

    public function delete(int $id): void
    {
        $driver = Driver::query()->findOrFail($id);
        Gate::authorize('delete', $driver);
        $driver->delete();
        session()->flash('status', __('Motorista removido.'));
    }

    public function render()
    {
        Gate::authorize('viewAny', Driver::class);

        return view('livewire.drivers.driver-index', [
            'drivers' => Driver::query()->with('linkedUser')->orderBy('name')->paginate(10),
        ]);
    }
}
