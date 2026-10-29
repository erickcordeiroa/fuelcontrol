<?php

namespace App\Livewire\Drivers;

use App\Enums\UserRole;
use App\Livewire\Concerns\ChecksPlanLimits;
use App\Livewire\Concerns\ConfirmsDeletes;
use App\Models\Driver;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Motoristas')]
class DriverIndex extends Component
{
    use ChecksPlanLimits, ConfirmsDeletes;
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $license_number = '';

    public string $phone = '';

    /** Senha do app do motorista; em branco na edição mantém a atual. */
    public string $app_password = '';

    public function openCreateModal(): void
    {
        Gate::authorize('create', Driver::class);

        if (! $this->planAllowsAdding('drivers')) {
            return;
        }

        $this->editingId = null;
        $this->resetForm();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $driver = Driver::query()->findOrFail($id);
        Gate::authorize('update', $driver);

        $this->editingId = $driver->id;
        $this->name = $driver->name;
        $this->license_number = $driver->license_number;
        $this->phone = (string) ($driver->phone ?? '');
        $this->app_password = '';
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
        $this->app_password = '';
    }

    public function save(): void
    {
        $driver = $this->editingId === null ? null : Driver::query()->findOrFail($this->editingId);

        if ($driver === null) {
            Gate::authorize('create', Driver::class);

            if (! $this->planAllowsAdding('drivers')) {
                return;
            }
        } else {
            Gate::authorize('update', $driver);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:64'],
            'phone' => [...$this->phoneRules($driver?->id), 'required_with:app_password'],
            'app_password' => ['nullable', 'string', 'min:6', 'max:255'],
        ], [
            'phone.required_with' => __('Informe o celular: ele é o login do app.'),
        ], [
            'app_password' => __('senha do app'),
        ]);

        DB::transaction(function () use ($driver, $validated): void {
            $attributes = [
                'name' => $validated['name'],
                'license_number' => $validated['license_number'],
                'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
            ];

            $driver === null
                ? $driver = Driver::query()->create($attributes)
                : $driver->update($attributes);

            $this->syncAppAccess($driver, $validated['app_password'] ?? '');
        });

        session()->flash('status', $this->editingId === null ? __('Motorista criado.') : __('Motorista atualizado.'));

        $this->closeModal();
    }

    /**
     * Cria ou atualiza o acesso do motorista ao app. Senha nova encerra as sessões abertas no celular.
     */
    private function syncAppAccess(Driver $driver, string $password): void
    {
        $user = $driver->linkedUser;

        if ($user !== null) {
            $user->update(['name' => $driver->name]);
        }

        if ($password === '') {
            return;
        }

        if ($user === null) {
            $user = User::query()->create([
                'name' => $driver->name,
                'email' => null,
                'password' => $password,
                'role' => UserRole::Driver,
            ]);
            $driver->update(['linked_user_id' => $user->id]);

            return;
        }

        $user->update(['password' => $password]);
        $user->tokens()->delete();
    }

    /**
     * O celular é o login do app do motorista (spec 004): com DDD e sem repetir na mesma empresa.
     *
     * @return list<mixed>
     */
    private function phoneRules(?int $ignoreDriverId = null): array
    {
        return [
            'nullable',
            'string',
            'max:32',
            function (string $attribute, mixed $value, Closure $fail) use ($ignoreDriverId): void {
                $digits = Driver::normalizePhone((string) $value);

                if ($digits === null) {
                    return;
                }

                if (strlen($digits) < 10 || strlen($digits) > 11) {
                    $fail(__('Informe o celular com DDD, por exemplo (13) 99722-1580.'));

                    return;
                }

                $taken = Driver::query()
                    ->where('phone', $digits)
                    ->when($ignoreDriverId !== null, fn ($query) => $query->whereKeyNot($ignoreDriverId))
                    ->exists();

                if ($taken) {
                    $fail(__('Este celular já está em outro motorista.'));
                }
            },
        ];
    }

    public function delete(int $id): void
    {
        $driver = Driver::query()->with('linkedUser')->findOrFail($id);
        Gate::authorize('delete', $driver);

        DB::transaction(function () use ($driver): void {
            $access = $driver->linkedUser;
            $driver->delete();

            if ($access !== null && $access->role === UserRole::Driver) {
                $access->tokens()->delete();
                $access->delete();
            }
        });

        if ($this->showModal && $this->editingId === $id) {
            $this->closeModal();
        }

        session()->flash('status', __('Motorista removido.'));
    }

    public function render()
    {
        Gate::authorize('viewAny', Driver::class);

        return view('livewire.drivers.driver-index', [
            'drivers' => Driver::query()->orderBy('name')->paginate(10),
            'hasAppAccess' => $this->editingId !== null && Driver::query()->whereKey($this->editingId)->whereNotNull('linked_user_id')->exists(),
        ]);
    }
}
