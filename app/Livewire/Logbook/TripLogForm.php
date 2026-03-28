<?php

namespace App\Livewire\Logbook;

use App\Models\Driver;
use App\Models\GasStation;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\TripService;
use App\Support\BrazilianNumber;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    public ?int $gas_station_id = null;

    public string $liters = '0,00';

    public string $price_per_liter = '0,0000';

    public string $station = '';

    public string $toll = '0,00';

    public string $assistant = '0,00';

    public string $food = '0,00';

    public function mount(): void
    {
        Gate::authorize('create', Trip::class);

        $this->date = now()->toDateString();

        $user = auth()->user();
        if (! $user->isAdmin() && $user->driver !== null) {
            $this->driver_id = $user->driver->id;
        }
    }

    public function updatedGasStationId(?int $value): void
    {
        if ($value === null) {
            return;
        }

        $gasStation = GasStation::query()->find($value);
        if ($gasStation !== null) {
            $this->price_per_liter = BrazilianNumber::format((float) $gasStation->price_per_liter, 4);
            $this->station = $gasStation->name;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizedPayload(): array
    {
        return [
            'date' => $this->date,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'km_start' => $this->km_start,
            'km_end' => $this->km_end,
            'gas_station_id' => $this->gas_station_id ? (int) $this->gas_station_id : null,
            'liters' => BrazilianNumber::parse($this->liters),
            'price_per_liter' => $this->resolvedPricePerLiterForPayload(),
            'station' => $this->station,
            'toll' => BrazilianNumber::parse($this->toll),
            'assistant' => BrazilianNumber::parse($this->assistant),
            'food' => BrazilianNumber::parse($this->food),
        ];
    }

    /**
     * When a registered posto is selected, the price always comes from the database (field is read-only in the UI).
     */
    private function resolvedPricePerLiterForPayload(): float
    {
        if ($this->gas_station_id === null) {
            return BrazilianNumber::parse($this->price_per_liter);
        }

        $gasStation = GasStation::query()->find($this->gas_station_id);

        return $gasStation !== null
            ? (float) $gasStation->price_per_liter
            : BrazilianNumber::parse($this->price_per_liter);
    }

    /**
     * @return array<string, array<int, string|\Closure>>
     */
    protected function rulesForNormalizedPayload(): array
    {
        $tenantId = auth()->user()->tenantOwnerId();

        return [
            'date' => ['required', 'date'],
            'vehicle_id' => [
                'required',
                Rule::exists('vehicles', 'id')->where(fn ($q) => $q->where('user_id', $tenantId)),
            ],
            'driver_id' => [
                'required',
                Rule::exists('drivers', 'id')->where(fn ($q) => $q->where('user_id', $tenantId)),
            ],
            'km_start' => ['required', 'integer', 'min:0'],
            'km_end' => ['required', 'integer', 'min:1'],
            'gas_station_id' => [
                'nullable',
                Rule::exists('gas_stations', 'id')->where(fn ($q) => $q->where('user_id', $tenantId)),
            ],
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
        $n = $this->normalizedPayload();

        return app(TripService::class)->previewTripMetrics([
            'km_start' => $n['km_start'],
            'km_end' => $n['km_end'],
            'liters' => $n['liters'],
            'price_per_liter' => $n['price_per_liter'],
            'revenue' => 0,
            'toll' => $n['toll'],
            'assistant' => $n['assistant'],
            'food' => $n['food'],
        ]);
    }

    public function save(): void
    {
        $validated = Validator::make($this->normalizedPayload(), $this->rulesForNormalizedPayload())->validate();

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
            'gas_station_id' => $validated['gas_station_id'] ?? null,
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
                $q->where('linked_user_id', auth()->id());
            })
            ->orderBy('name')
            ->get();

        $gasStations = GasStation::query()->orderBy('name')->get();

        return view('livewire.logbook.trip-log-form', [
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'gasStations' => $gasStations,
        ]);
    }
}
