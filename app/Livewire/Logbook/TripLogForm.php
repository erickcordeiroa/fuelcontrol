<?php

namespace App\Livewire\Logbook;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\TripService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Diário de Bordo')]
class TripLogForm extends Component
{
    public string $date = '';

    public ?int $vehicle_id = null;

    public ?int $driver_id = null;

    public ?int $km_start = null;

    public ?int $km_end = null;

    public string $liters = '0';

    public string $price_per_liter = '0';

    public string $station = '';

    public string $toll = '0';

    public string $assistant = '0';

    public string $food = '0';

    public function mount(): void
    {
        Gate::authorize('create', Trip::class);

        $this->date = now()->toDateString();

        $user = auth()->user();
        if (! $user->isAdmin() && $user->driver !== null) {
            $this->driver_id = $user->driver->id;
        }
    }

    protected function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'km_start' => ['required', 'integer', 'min:0'],
            'km_end' => ['required', 'integer', 'min:1'],
            'liters' => ['required', 'numeric', 'min:0'],
            'price_per_liter' => ['required', 'numeric', 'min:0'],
            'station' => ['nullable', 'string', 'max:255'],
            'toll' => ['required', 'numeric', 'min:0'],
            'assistant' => ['required', 'numeric', 'min:0'],
            'food' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array{km_total: int|null, fuel_cost: float, total_expenses: float, net_margin: float|null, efficiency_km_per_liter: float|null, cost_per_km: float|null}
     */
    #[Computed]
    public function preview(): array
    {
        return app(TripService::class)->previewTripMetrics([
            'km_start' => $this->km_start,
            'km_end' => $this->km_end,
            'liters' => $this->liters,
            'price_per_liter' => $this->price_per_liter,
            'revenue' => 0,
            'toll' => $this->toll,
            'assistant' => $this->assistant,
            'food' => $this->food,
        ]);
    }

    public function save(): void
    {
        $validated = $this->validate();

        app(TripService::class)->createTrip(auth()->user(), [
            'date' => $validated['date'],
            'vehicle_id' => (int) $validated['vehicle_id'],
            'driver_id' => (int) $validated['driver_id'],
            'km_start' => (int) $validated['km_start'],
            'km_end' => (int) $validated['km_end'],
            'revenue' => 0,
            'liters' => $validated['liters'],
            'price_per_liter' => $validated['price_per_liter'],
            'station' => $validated['station'] !== '' ? $validated['station'] : null,
            'toll' => $validated['toll'],
            'assistant' => $validated['assistant'],
            'food' => $validated['food'],
        ]);

        session()->flash('status', __('Registro salvo com sucesso.'));
        $this->redirect(route('logbook'), navigate: true);
    }

    public function render()
    {
        $vehicles = Vehicle::query()->orderBy('plate')->get();
        $drivers = Driver::query()
            ->when(! auth()->user()->isAdmin(), function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->orderBy('name')
            ->get();

        return view('livewire.logbook.trip-log-form', [
            'vehicles' => $vehicles,
            'drivers' => $drivers,
        ]);
    }
}
