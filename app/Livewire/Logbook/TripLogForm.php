<?php

namespace App\Livewire\Logbook;

use App\Enums\ExpenseType;
use App\Enums\FuelType;
use App\Models\Driver;
use App\Models\GasStation;
use App\Models\GasStationFuelOffering;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\TripService;
use App\Support\BrazilianNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TripLogForm extends Component
{
    public ?int $editingTripId = null;

    public string $date = '';

    public string $trip_time = '';

    public string $notes = '';

    public ?int $vehicle_id = null;

    public ?int $driver_id = null;

    public ?int $km_start = null;

    public ?int $km_end = null;

    public ?int $gas_station_id = null;

    public ?int $gas_station_fuel_offering_id = null;

    public string $liters = '0,00';

    public string $price_per_liter = '0,00';

    public string $original_price_per_liter = '0,00';

    public string $station = '';

    /** Quando verdadeiro, pedágio, ajudante e alimentação podem ser informados. */
    public bool $is_daily_operation = false;

    public string $toll = '0,00';

    public string $assistant = '0,00';

    public string $food = '0,00';

    public bool $showPriceUpdateModal = false;

    /**
     * @var array<string, mixed>
     */
    public array $pendingSavePayload = [];

    public string $priceUpdateStationName = '';

    public string $priceUpdateFuelName = '';

    public string $priceUpdateFrom = '0,00';

    public string $priceUpdateTo = '0,00';

    public function mount(?Trip $trip = null): void
    {
        if ($trip !== null) {
            Gate::authorize('update', $trip);
            $this->editingTripId = $trip->id;
            $this->fillFromTrip($trip);

            return;
        }

        Gate::authorize('create', Trip::class);

        $this->date = now()->toDateString();
        $this->trip_time = now()->format('H:i');

        $user = auth()->user();
        if (! $user->isAdmin() && $user->driver !== null) {
            $this->driver_id = $user->driver->id;
        }
    }

    private function fillFromTrip(Trip $trip): void
    {
        $trip->loadMissing(['fuel', 'expenses']);
        $fuel = $trip->fuel;

        if ($fuel === null) {
            abort(404);
        }

        $this->date = $trip->date->toDateString();
        $this->trip_time = $trip->trip_time ? (string) $trip->trip_time : '';
        $this->notes = $trip->notes ?? '';
        $this->vehicle_id = $trip->vehicle_id;
        $this->driver_id = $trip->driver_id;
        $this->km_start = $trip->km_start;
        $this->km_end = $trip->km_end;
        $this->gas_station_id = $fuel->gas_station_id;
        $this->gas_station_fuel_offering_id = $fuel->gas_station_fuel_offering_id;
        $this->liters = BrazilianNumber::format((float) $fuel->liters, 2);
        $this->price_per_liter = BrazilianNumber::format((float) $fuel->price_per_liter, 2);
        $this->original_price_per_liter = $this->price_per_liter;
        $this->station = $fuel->station ?? '';

        $toll = $trip->expenseAmountFor(ExpenseType::Toll);
        $assistant = $trip->expenseAmountFor(ExpenseType::Assistant);
        $food = $trip->expenseAmountFor(ExpenseType::Food);

        $this->is_daily_operation = $toll > 0 || $assistant > 0 || $food > 0;
        $this->toll = BrazilianNumber::format($toll, 2);
        $this->assistant = BrazilianNumber::format($assistant, 2);
        $this->food = BrazilianNumber::format($food, 2);
    }

    public function updatedVehicleId(?int $value): void
    {
        if ($this->editingTripId !== null) {
            return;
        }

        if ($value === null) {
            $this->km_start = null;

            return;
        }

        $lastKmEnd = Trip::query()
            ->where('vehicle_id', $value)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('km_end');

        $this->km_start = $lastKmEnd !== null ? (int) $lastKmEnd : null;
    }

    public function updatedGasStationId(?int $value): void
    {
        $this->gas_station_fuel_offering_id = null;
        $this->price_per_liter = '0,00';
        $this->original_price_per_liter = '0,00';

        if ($value === null) {
            $this->station = '';

            return;
        }

        $gasStation = GasStation::query()->find($value);
        if ($gasStation !== null) {
            $this->station = $gasStation->name;
        }
    }

    public function updatedGasStationFuelOfferingId(?int $value): void
    {
        if ($value === null || $this->gas_station_id === null) {
            $this->price_per_liter = '0,00';

            return;
        }

        $offering = $this->selectedGasStationFuelOffering();

        if ($offering !== null) {
            $this->price_per_liter = BrazilianNumber::format((float) $offering->price_per_liter, 2);
            $this->original_price_per_liter = $this->price_per_liter;
        } else {
            $this->price_per_liter = '0,00';
            $this->original_price_per_liter = '0,00';
        }
    }

    public function updatedIsDailyOperation(mixed $value): void
    {
        if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->toll = '0,00';
            $this->assistant = '0,00';
            $this->food = '0,00';
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizedPayload(): array
    {
        $toll = $this->is_daily_operation ? BrazilianNumber::parse($this->toll) : 0.0;
        $assistant = $this->is_daily_operation ? BrazilianNumber::parse($this->assistant) : 0.0;
        $food = $this->is_daily_operation ? BrazilianNumber::parse($this->food) : 0.0;

        return [
            'date' => $this->date,
            'trip_time' => $this->normalizedTripTime(),
            'notes' => $this->normalizedNotes(),
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'km_start' => $this->km_start,
            'km_end' => $this->km_end,
            'gas_station_id' => $this->gas_station_id ? (int) $this->gas_station_id : null,
            'gas_station_fuel_offering_id' => $this->gas_station_id ? $this->gas_station_fuel_offering_id : null,
            'fuel_type' => $this->resolvedFuelTypeValue(),
            'liters' => BrazilianNumber::parse($this->liters),
            'price_per_liter' => $this->resolvedPricePerLiterForPayload(),
            'station' => $this->station,
            'toll' => $toll,
            'assistant' => $assistant,
            'food' => $food,
        ];
    }

    private function normalizedTripTime(): ?string
    {
        $t = trim($this->trip_time);
        if ($t === '') {
            return null;
        }

        if (preg_match('/^(\d{2}:\d{2}):\d{2}$/', $t, $m)) {
            return $m[1];
        }

        return $t;
    }

    private function normalizedNotes(): ?string
    {
        $n = trim($this->notes);

        return $n === '' ? null : $n;
    }

    private function resolvedFuelTypeValue(): string
    {
        if ($this->gas_station_id !== null) {
            if ($this->gas_station_fuel_offering_id === null) {
                return '';
            }

            $offering = $this->selectedGasStationFuelOffering();

            return $offering !== null ? $offering->fuel_type->value : '';
        }

        return FuelType::Outro->value;
    }

    private function resolvedPricePerLiterForPayload(): float
    {
        return BrazilianNumber::parse($this->price_per_liter);
    }

    private function selectedGasStationFuelOffering(): ?GasStationFuelOffering
    {
        if ($this->gas_station_id === null || $this->gas_station_fuel_offering_id === null) {
            return null;
        }

        return GasStationFuelOffering::query()
            ->whereKey($this->gas_station_fuel_offering_id)
            ->where('gas_station_id', $this->gas_station_id)
            ->first();
    }

    /**
     * @return array<string, array<int, string|Enum|\Closure>>
     */
    protected function rulesForNormalizedPayload(): array
    {
        $tenantId = auth()->user()->tenantOwnerId();

        $gasOfferingRules = ['nullable', 'integer'];
        if ($this->gas_station_id !== null) {
            $gasOfferingRules[] = 'required';
            $gasOfferingRules[] = Rule::exists('gas_station_fuel_offerings', 'id')
                ->where('gas_station_id', (int) $this->gas_station_id);
        }

        return [
            'date' => ['required', 'date'],
            'trip_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
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
            'gas_station_fuel_offering_id' => $gasOfferingRules,
            'fuel_type' => ['required', Rule::enum(FuelType::class)],
            'liters' => ['required', 'numeric', 'min:0'],
            'price_per_liter' => ['required', 'numeric', 'min:0'],
            'station' => ['nullable', 'string', 'max:255'],
            'toll' => $this->dailyExpenseFieldRules(),
            'assistant' => $this->dailyExpenseFieldRules(),
            'food' => $this->dailyExpenseFieldRules(),
        ];
    }

    /**
     * @return list<string>
     */
    private function dailyExpenseFieldRules(): array
    {
        if ($this->is_daily_operation) {
            return ['required', 'numeric', 'min:0'];
        }

        return ['sometimes', 'numeric', 'min:0'];
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

        if ($this->shouldConfirmGasStationPriceUpdate($validated)) {
            $this->openPriceUpdateModal($validated);

            return;
        }

        $this->persistTrip($validated);
    }

    public function cancelPriceUpdate(): void
    {
        if ($this->pendingSavePayload === []) {
            $this->closePriceUpdateModal();

            return;
        }

        $validated = $this->pendingSavePayload;
        $this->closePriceUpdateModal();
        $this->persistTrip($validated);
    }

    public function confirmPriceUpdate(): void
    {
        if ($this->pendingSavePayload === []) {
            $this->closePriceUpdateModal();

            return;
        }

        $validated = $this->pendingSavePayload;
        $this->closePriceUpdateModal();
        $this->persistTrip($validated, true);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function persistTrip(array $validated, bool $updateGasStationPrice = false): void
    {
        $user = auth()->user();

        DB::transaction(function () use ($validated, $user, $updateGasStationPrice): void {
            if ($this->editingTripId !== null) {
                $trip = Trip::query()->findOrFail($this->editingTripId);
                Gate::authorize('update', $trip);

                app(TripService::class)->updateTrip($user, $trip, [
                    'date' => $validated['date'],
                    'trip_time' => $validated['trip_time'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'vehicle_id' => (int) $validated['vehicle_id'],
                    'driver_id' => (int) $validated['driver_id'],
                    'km_start' => (int) $validated['km_start'],
                    'km_end' => (int) $validated['km_end'],
                    'revenue' => $trip->revenue,
                    'liters' => $validated['liters'],
                    'price_per_liter' => $validated['price_per_liter'],
                    'fuel_type' => $validated['fuel_type'],
                    'station' => $validated['station'] !== '' ? $validated['station'] : null,
                    'gas_station_id' => $validated['gas_station_id'] ?? null,
                    'gas_station_fuel_offering_id' => $validated['gas_station_fuel_offering_id'] ?? null,
                    'toll' => $validated['toll'],
                    'assistant' => $validated['assistant'],
                    'food' => $validated['food'],
                ]);

                $this->syncGasStationOfferingPriceWhenConfirmed($validated, $updateGasStationPrice);

                session()->flash('status', __('Registro atualizado com sucesso. O histórico de alterações foi salvo.'));
                $this->redirect(route('reports'), navigate: true);

                return;
            }

            app(TripService::class)->createTrip($user, [
                'date' => $validated['date'],
                'trip_time' => $validated['trip_time'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'vehicle_id' => (int) $validated['vehicle_id'],
                'driver_id' => (int) $validated['driver_id'],
                'km_start' => (int) $validated['km_start'],
                'km_end' => (int) $validated['km_end'],
                'revenue' => 0,
                'liters' => $validated['liters'],
                'price_per_liter' => $validated['price_per_liter'],
                'fuel_type' => $validated['fuel_type'],
                'station' => $validated['station'] !== '' ? $validated['station'] : null,
                'gas_station_id' => $validated['gas_station_id'] ?? null,
                'gas_station_fuel_offering_id' => $validated['gas_station_fuel_offering_id'] ?? null,
                'toll' => $validated['toll'],
                'assistant' => $validated['assistant'],
                'food' => $validated['food'],
            ]);

            $this->syncGasStationOfferingPriceWhenConfirmed($validated, $updateGasStationPrice);

            session()->flash('status', __('Registro salvo com sucesso.'));
            $this->redirect(route('logbook'), navigate: true);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function shouldConfirmGasStationPriceUpdate(array $validated): bool
    {
        if (($validated['gas_station_id'] ?? null) === null || ($validated['gas_station_fuel_offering_id'] ?? null) === null) {
            return false;
        }

        $originalPricePerLiter = BrazilianNumber::parse($this->original_price_per_liter);

        if (round($originalPricePerLiter, 2) === round((float) $validated['price_per_liter'], 2)) {
            return false;
        }

        $offering = $this->selectedGasStationFuelOffering();

        if ($offering === null) {
            return false;
        }

        return round((float) $offering->price_per_liter, 2) !== round((float) $validated['price_per_liter'], 2);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function openPriceUpdateModal(array $validated): void
    {
        $offering = $this->selectedGasStationFuelOffering();

        if ($offering === null) {
            $this->persistTrip($validated);

            return;
        }

        $this->pendingSavePayload = $validated;
        $this->priceUpdateStationName = $this->station !== '' ? $this->station : ($offering->gasStation?->name ?? '');
        $this->priceUpdateFuelName = $offering->fuel_type->label();
        $this->priceUpdateFrom = BrazilianNumber::format((float) $offering->price_per_liter, 2);
        $this->priceUpdateTo = BrazilianNumber::format((float) $validated['price_per_liter'], 2);
        $this->showPriceUpdateModal = true;
    }

    private function closePriceUpdateModal(): void
    {
        $this->showPriceUpdateModal = false;
        $this->pendingSavePayload = [];
        $this->priceUpdateStationName = '';
        $this->priceUpdateFuelName = '';
        $this->priceUpdateFrom = '0,00';
        $this->priceUpdateTo = '0,00';
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncGasStationOfferingPriceWhenConfirmed(array $validated, bool $updateGasStationPrice): void
    {
        if (! $updateGasStationPrice) {
            return;
        }

        if (($validated['gas_station_id'] ?? null) === null || ($validated['gas_station_fuel_offering_id'] ?? null) === null) {
            return;
        }

        $offering = GasStationFuelOffering::query()
            ->whereKey((int) $validated['gas_station_fuel_offering_id'])
            ->where('gas_station_id', (int) $validated['gas_station_id'])
            ->first();

        if ($offering === null) {
            return;
        }

        $offering->update([
            'price_per_liter' => round((float) $validated['price_per_liter'], 2),
        ]);
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

        $gasStations = GasStation::query()->with('fuelOfferings')->orderBy('name')->get();

        $pageTitle = $this->editingTripId !== null
            ? __('Editar registro do diário')
            : __('Diário de Bordo');

        return view('livewire.logbook.trip-log-form', [
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'gasStations' => $gasStations,
        ])->title($pageTitle);
    }
}
