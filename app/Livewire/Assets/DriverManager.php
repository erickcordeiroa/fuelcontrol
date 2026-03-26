<?php

namespace App\Livewire\Assets;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Motoristas')]
class DriverManager extends Component
{
    use WithPagination;

    public string $name = '';

    public string $license_number = '';

    public string $phone = '';

    public ?int $user_id = null;

    public ?int $editingId = null;

    public function startEdit(int $id): void
    {
        $driver = Driver::query()->findOrFail($id);
        Gate::authorize('update', $driver);

        $this->editingId = $driver->id;
        $this->name = $driver->name;
        $this->license_number = $driver->license_number;
        $this->phone = (string) ($driver->phone ?? '');
        $this->user_id = $driver->user_id;
    }

    public function save(): void
    {
        if ($this->user_id === '' || $this->user_id === 0) {
            $this->user_id = null;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', UserRole::Driver->value)),
                Rule::unique('drivers', 'user_id')->ignore($this->editingId),
            ],
        ]);

        if ($this->editingId === null) {
            Gate::authorize('create', Driver::class);
            Driver::query()->create([
                'name' => $validated['name'],
                'license_number' => $validated['license_number'],
                'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
                'user_id' => $validated['user_id'],
            ]);
            session()->flash('status', __('Motorista criado.'));
        } else {
            $driver = Driver::query()->findOrFail($this->editingId);
            Gate::authorize('update', $driver);
            $driver->update([
                'name' => $validated['name'],
                'license_number' => $validated['license_number'],
                'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
                'user_id' => $validated['user_id'],
            ]);
            session()->flash('status', __('Motorista atualizado.'));
        }

        $this->resetForm();
        $this->editingId = null;
    }

    public function delete(int $id): void
    {
        $driver = Driver::query()->findOrFail($id);
        Gate::authorize('delete', $driver);
        $driver->delete();
        session()->flash('status', __('Motorista removido.'));
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->editingId = null;
    }

    protected function resetForm(): void
    {
        $this->name = '';
        $this->license_number = '';
        $this->phone = '';
        $this->user_id = null;
    }

    public function render()
    {
        $drivers = Driver::query()->with('user')->orderBy('name')->paginate(10);

        $linkableUsers = User::query()
            ->where('role', UserRole::Driver)
            ->where(function ($q) {
                $q->whereDoesntHave('driver')
                    ->orWhere('id', $this->user_id);
            })
            ->orderBy('name')
            ->get();

        return view('livewire.assets.driver-manager', [
            'drivers' => $drivers,
            'linkableUsers' => $linkableUsers,
        ]);
    }
}
