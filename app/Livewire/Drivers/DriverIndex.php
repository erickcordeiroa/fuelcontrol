<?php

namespace App\Livewire\Drivers;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Motoristas')]
class DriverIndex extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $license_number = '';

    public string $phone = '';

    public ?int $linked_user_id = null;

    public function openCreateModal(): void
    {
        Gate::authorize('create', Driver::class);

        $this->editingId = null;
        $this->resetForm();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $driver = Driver::query()->with('linkedUser')->findOrFail($id);
        Gate::authorize('update', $driver);

        $this->editingId = $driver->id;
        $this->name = $driver->name;
        $this->license_number = $driver->license_number;
        $this->phone = (string) ($driver->phone ?? '');
        $this->linked_user_id = $driver->linked_user_id;
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
        $this->license_number = '';
        $this->phone = '';
        $this->linked_user_id = null;
    }

    public function save(): void
    {
        if (! $this->linked_user_id) {
            $this->linked_user_id = null;
        }

        if ($this->editingId === null) {
            Gate::authorize('create', Driver::class);

            $validated = $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'license_number' => ['required', 'string', 'max:64'],
                'phone' => ['nullable', 'string', 'max:32'],
                'linked_user_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', UserRole::Driver->value)),
                    Rule::unique('drivers', 'linked_user_id'),
                ],
            ]);

            Driver::query()->create([
                'name' => $validated['name'],
                'license_number' => $validated['license_number'],
                'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
                'linked_user_id' => $validated['linked_user_id'],
            ]);

            session()->flash('status', __('Motorista criado.'));
        } else {
            $driver = Driver::query()->findOrFail($this->editingId);
            Gate::authorize('update', $driver);

            $validated = $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'license_number' => ['required', 'string', 'max:64'],
                'phone' => ['nullable', 'string', 'max:32'],
                'linked_user_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', UserRole::Driver->value)),
                    Rule::unique('drivers', 'linked_user_id')->ignore($driver->id),
                ],
            ]);

            $driver->update([
                'name' => $validated['name'],
                'license_number' => $validated['license_number'],
                'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
                'linked_user_id' => $validated['linked_user_id'],
            ]);

            session()->flash('status', __('Motorista atualizado.'));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $driver = Driver::query()->findOrFail($id);
        Gate::authorize('delete', $driver);
        $driver->delete();

        if ($this->showModal && $this->editingId === $id) {
            $this->closeModal();
        }

        session()->flash('status', __('Motorista removido.'));
    }

    /**
     * @return Collection<int, User>
     */
    private function linkableUsers(): Collection
    {
        return User::query()
            ->where('role', UserRole::Driver)
            ->where(function ($q) {
                $q->whereDoesntHave('driver')
                    ->when($this->linked_user_id, fn ($q2) => $q2->orWhere('id', $this->linked_user_id));
            })
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        Gate::authorize('viewAny', Driver::class);

        return view('livewire.drivers.driver-index', [
            'drivers' => Driver::query()->with('linkedUser')->orderBy('name')->paginate(10),
            'linkableUsers' => $this->linkableUsers(),
        ]);
    }
}
