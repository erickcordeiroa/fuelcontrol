<?php

namespace App\Livewire\GasStations;

use App\Models\GasStation;
use App\Support\BrazilianNumber;
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

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $price_per_liter = '0,0000';

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
        $gasStation = GasStation::query()->findOrFail($id);
        Gate::authorize('update', $gasStation);

        $this->editingId = $gasStation->id;
        $this->name = $gasStation->name;
        $this->phone = (string) ($gasStation->phone ?? '');
        $this->address = (string) ($gasStation->address ?? '');
        $this->price_per_liter = BrazilianNumber::format((float) $gasStation->price_per_liter, 4);
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
        $this->name = '';
        $this->phone = '';
        $this->address = '';
        $this->price_per_liter = '0,0000';
    }

    public function save(): void
    {
        if ($this->editingId === null) {
            Gate::authorize('create', GasStation::class);
        } else {
            $gasStation = GasStation::query()->findOrFail($this->editingId);
            Gate::authorize('update', $gasStation);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
            'price_per_liter' => ['required', 'string'],
        ]);

        $price = BrazilianNumber::parse($validated['price_per_liter']);

        if ($price < 0) {
            $this->addError('price_per_liter', __('O valor do litro deve ser zero ou positivo.'));

            return;
        }

        $payload = [
            'name' => $validated['name'],
            'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
            'address' => $validated['address'] !== '' ? $validated['address'] : null,
            'price_per_liter' => round($price, 4),
        ];

        if ($this->editingId === null) {
            GasStation::query()->create($payload);
            session()->flash('status', __('Posto criado.'));
        } else {
            GasStation::query()->findOrFail($this->editingId)->update($payload);
            session()->flash('status', __('Posto atualizado.'));
        }

        $this->closeModal();
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
            'gasStations' => GasStation::query()->orderBy('name')->paginate(10),
        ]);
    }
}
