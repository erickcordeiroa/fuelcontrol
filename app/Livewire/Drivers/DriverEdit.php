<?php

namespace App\Livewire\Drivers;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Editar motorista')]
class DriverEdit extends Component
{
    public Driver $driver;

    public string $name = '';

    public string $license_number = '';

    public string $phone = '';

    public ?int $linked_user_id = null;

    public function mount(Driver $driver): void
    {
        Gate::authorize('update', $driver);

        $this->driver = $driver;
        $this->name = $driver->name;
        $this->license_number = $driver->license_number;
        $this->phone = (string) ($driver->phone ?? '');
        $this->linked_user_id = $driver->linked_user_id;
    }

    public function save(): void
    {
        Gate::authorize('update', $this->driver);

        if ($this->linked_user_id === '' || $this->linked_user_id === 0) {
            $this->linked_user_id = null;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'linked_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', UserRole::Driver->value)),
                Rule::unique('drivers', 'linked_user_id')->ignore($this->driver->id),
            ],
        ]);

        $this->driver->update([
            'name' => $validated['name'],
            'license_number' => $validated['license_number'],
            'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
            'linked_user_id' => $validated['linked_user_id'],
        ]);

        session()->flash('status', __('Motorista atualizado.'));
        $this->redirect(route('drivers.index'), navigate: true);
    }

    public function render()
    {
        $linkableUsers = User::query()
            ->where('role', UserRole::Driver)
            ->where(function ($q) {
                $q->whereDoesntHave('driver')
                    ->orWhere('id', $this->linked_user_id);
            })
            ->orderBy('name')
            ->get();

        return view('livewire.drivers.driver-edit', [
            'linkableUsers' => $linkableUsers,
        ]);
    }
}
