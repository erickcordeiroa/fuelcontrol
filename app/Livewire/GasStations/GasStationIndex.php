<?php

namespace App\Livewire\GasStations;

use App\Enums\FuelType;
use App\Livewire\Concerns\ConfirmsDeletes;
use App\Models\GasStation;
use App\Models\GasStationFuelOffering;
use App\Support\BrazilianNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Postos')]
class GasStationIndex extends Component
{
    use ConfirmsDeletes;
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    /**
     * Chaves string (UUID) evitam Livewire interpretar `fuel_offerings.0.price_per_liter` como método.
     *
     * @var array<string, array{id: int|null, fuel_type: string, price_per_liter: string}>
     */
    public array $fuel_offerings = [];

    public function openCreateModal(): void
    {
        Gate::authorize('create', GasStation::class);

        $this->editingId = null;
        $this->resetForm();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $gasStation = GasStation::query()->with('fuelOfferings')->findOrFail($id);
        Gate::authorize('update', $gasStation);

        $this->editingId = $gasStation->id;
        $this->name = $gasStation->name;
        $this->phone = (string) ($gasStation->phone ?? '');
        $this->address = (string) ($gasStation->address ?? '');

        $rows = [];
        foreach ($gasStation->fuelOfferings as $o) {
            $rows[(string) Str::uuid()] = [
                'id' => $o->id,
                'fuel_type' => $o->fuel_type->value,
                'price_per_liter' => BrazilianNumber::format((float) $o->price_per_liter, 2),
            ];
        }

        $this->fuel_offerings = $rows !== [] ? $rows : $this->defaultFuelOfferingRows();

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

    public function addFuelOfferingRow(): void
    {
        $this->fuel_offerings[(string) Str::uuid()] = [
            'id' => null,
            'fuel_type' => FuelType::GasolinaComum->value,
            'price_per_liter' => '0,00',
        ];
    }

    public function removeFuelOfferingRow(string $key): void
    {
        unset($this->fuel_offerings[$key]);

        if ($this->fuel_offerings === []) {
            $this->fuel_offerings = $this->defaultFuelOfferingRows();
        }
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->phone = '';
        $this->address = '';
        $this->fuel_offerings = $this->defaultFuelOfferingRows();
    }

    /**
     * @return array<string, array{id: null, fuel_type: string, price_per_liter: string}>
     */
    private function defaultFuelOfferingRows(): array
    {
        return [
            (string) Str::uuid() => [
                'id' => null,
                'fuel_type' => FuelType::GasolinaComum->value,
                'price_per_liter' => '0,00',
            ],
        ];
    }

    public function save(): void
    {
        if ($this->editingId === null) {
            Gate::authorize('create', GasStation::class);
        } else {
            $gasStation = GasStation::query()->findOrFail($this->editingId);
            Gate::authorize('update', $gasStation);
        }

        $validated = $this->validate($this->fuelOfferingValidationRules(), [], [
            'fuel_offerings' => __('Combustíveis e preços'),
        ]);

        $fuelTypes = collect($validated['fuel_offerings'])->pluck('fuel_type');
        if ($fuelTypes->unique()->count() !== $fuelTypes->count()) {
            $this->addError('fuel_offerings', __('Cada tipo de combustível pode aparecer apenas uma vez por posto.'));

            return;
        }

        foreach ($validated['fuel_offerings'] as $rowKey => $row) {
            $price = BrazilianNumber::parse($row['price_per_liter']);
            if ($price < 0) {
                $this->addError("fuel_offerings.{$rowKey}.price_per_liter", __('O valor do litro deve ser zero ou positivo.'));

                return;
            }
        }

        DB::transaction(function () use ($validated): void {
            if ($this->editingId === null) {
                $station = GasStation::query()->create([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
                    'address' => $validated['address'] !== '' ? $validated['address'] : null,
                ]);
            } else {
                $station = GasStation::query()->findOrFail($this->editingId);
                $station->update([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
                    'address' => $validated['address'] !== '' ? $validated['address'] : null,
                ]);
            }

            $keptIds = [];

            foreach ($validated['fuel_offerings'] as $row) {
                $price = round(BrazilianNumber::parse($row['price_per_liter']), 2);
                $fuelType = FuelType::from($row['fuel_type']);

                if (! empty($row['id'])) {
                    $offering = GasStationFuelOffering::query()
                        ->where('gas_station_id', $station->id)
                        ->whereKey((int) $row['id'])
                        ->firstOrFail();
                    $offering->update([
                        'fuel_type' => $fuelType,
                        'price_per_liter' => $price,
                    ]);
                    $keptIds[] = $offering->id;
                } else {
                    $created = $station->fuelOfferings()->create([
                        'fuel_type' => $fuelType,
                        'price_per_liter' => $price,
                    ]);
                    $keptIds[] = $created->id;
                }
            }

            $station->fuelOfferings()->whereNotIn('id', $keptIds)->delete();
        });

        session()->flash('status', $this->editingId === null ? __('Posto criado.') : __('Posto atualizado.'));
        $this->closeModal();
    }

    /**
     * @return array<string, array<int, string|Enum|ConditionalRules>>
     */
    protected function fuelOfferingValidationRules(): array
    {
        $idRule = $this->editingId === null
            ? ['nullable', 'integer']
            : ['nullable', 'integer', Rule::exists('gas_station_fuel_offerings', 'id')->where('gas_station_id', $this->editingId)];

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
            'fuel_offerings' => ['required', 'array', 'min:1'],
            'fuel_offerings.*.id' => $idRule,
            'fuel_offerings.*.fuel_type' => ['required', Rule::enum(FuelType::class)],
            'fuel_offerings.*.price_per_liter' => ['required', 'string'],
        ];
    }

    public function delete(int $id): void
    {
        $gasStation = GasStation::query()->findOrFail($id);
        Gate::authorize('delete', $gasStation);
        $gasStation->delete();

        if ($this->showModal && $this->editingId === $id) {
            $this->closeModal();
        }

        session()->flash('status', __('Posto removido.'));
    }

    public function render()
    {
        Gate::authorize('viewAny', GasStation::class);

        return view('livewire.gas-stations.gas-station-index', [
            'gasStations' => GasStation::query()->with('fuelOfferings')->orderBy('name')->paginate(10),
            'fuelTypeCases' => FuelType::orderedCases(),
        ]);
    }
}
