<?php

namespace App\Livewire\OilChanges;

use App\Livewire\Concerns\ConfirmsDeletes;
use App\Models\OilChange;
use App\Models\Vehicle;
use App\Services\OilChangeAlertService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Trocas de óleo')]
class OilChangeIndex extends Component
{
    use ConfirmsDeletes;
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $vehicleId = null;

    public string $date = '';

    public string $oil_brand = '';

    public string $interval_km = '5000';

    public string $notes = '';

    public function openCreateModal(): void
    {
        Gate::authorize('create', OilChange::class);

        $this->editingId = null;
        $this->resetForm();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $oilChange = OilChange::query()->findOrFail($id);
        Gate::authorize('update', $oilChange);

        $this->editingId = $oilChange->id;
        $this->vehicleId = (int) $oilChange->vehicle_id;
        $this->date = $oilChange->date->toDateString();
        $this->oil_brand = $oilChange->oil_brand;
        $this->interval_km = (string) $oilChange->interval_km;
        $this->notes = (string) ($oilChange->notes ?? '');
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
        $this->vehicleId = null;
        $this->date = '';
        $this->oil_brand = '';
        $this->interval_km = '5000';
        $this->notes = '';
    }

    public function save(): void
    {
        $tenantId = auth()->user()->tenantOwnerId();

        $rules = [
            'vehicleId' => ['required', 'integer', Rule::exists('vehicles', 'id')->where(fn ($q) => $q->where('user_id', $tenantId))],
            'date' => ['required', 'date_format:Y-m-d'],
            'oil_brand' => ['required', 'string', 'max:120'],
            'interval_km' => ['required', 'in:5000,10000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        if ($this->editingId !== null) {
            $oilChange = OilChange::query()->findOrFail($this->editingId);
            Gate::authorize('update', $oilChange);

            $validated = $this->validate($rules);

            $oilChange->update([
                'vehicle_id' => (int) $validated['vehicleId'],
                'date' => $validated['date'],
                'occurred_at' => Carbon::parse($validated['date'])->setTimeFrom(
                    Carbon::parse($oilChange->occurred_at ?? $oilChange->created_at ?? now()),
                ),
                'oil_brand' => $validated['oil_brand'],
                'interval_km' => (int) $validated['interval_km'],
                'notes' => $validated['notes'] !== '' ? $validated['notes'] : null,
            ]);

            session()->flash('status', __('Troca de óleo atualizada.'));
        } else {
            Gate::authorize('create', OilChange::class);

            $validated = $this->validate($rules);

            OilChange::query()->create([
                'vehicle_id' => (int) $validated['vehicleId'],
                'date' => $validated['date'],
                'occurred_at' => Carbon::parse($validated['date'])->setTimeFrom(now()),
                'oil_brand' => $validated['oil_brand'],
                'interval_km' => (int) $validated['interval_km'],
                'notes' => $validated['notes'] !== '' ? $validated['notes'] : null,
            ]);

            session()->flash('status', __('Troca de óleo registrada.'));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $oilChange = OilChange::query()->findOrFail($id);
        Gate::authorize('delete', $oilChange);
        $oilChange->delete();

        if ($this->showModal && $this->editingId === $id) {
            $this->closeModal();
        }

        session()->flash('status', __('Troca de óleo removida.'));
    }

    public function render()
    {
        Gate::authorize('viewAny', OilChange::class);

        $service = app(OilChangeAlertService::class);
        $latestOilChangeIdByVehicle = $service->latestOilChangeIdByVehicleId();

        $oilChanges = OilChange::query()
            ->with('vehicle')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(10);

        $oilChanges->getCollection()->transform(function (OilChange $oc) use ($service, $latestOilChangeIdByVehicle) {
            $oc->setAttribute('computed', $service->computeForRecord($oc));
            $vehicleId = (int) $oc->vehicle_id;
            $oc->setAttribute(
                'is_latest_oil_change_for_vehicle',
                isset($latestOilChangeIdByVehicle[$vehicleId])
                    && $latestOilChangeIdByVehicle[$vehicleId] === (int) $oc->id,
            );

            return $oc;
        });

        return view('livewire.oil-changes.oil-change-index', [
            'oilChanges' => $oilChanges,
            'vehicles' => Vehicle::query()->orderBy('plate')->get(),
        ]);
    }
}
