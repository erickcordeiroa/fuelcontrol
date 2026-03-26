<div class="mx-auto max-w-5xl space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-fleet-ink">{{ __('Veículos') }}</h1>
            <p class="mt-1 text-sm text-fleet-secondary">{{ __('Cadastro de placas e capacidade.') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('assets.drivers') }}" wire:navigate class="rounded-xl border border-fleet-border bg-fleet-card px-4 py-2 text-sm font-semibold text-fleet-ink hover:bg-fleet-page">
                {{ __('Motoristas') }}
            </a>
        </div>
    </div>

    <div class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">
            {{ $editingId ? __('Editar veículo') : __('Novo veículo') }}
        </h2>
        <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Placa') }}</label>
                <input type="text" wire:model="plate" class="mt-1 w-full rounded-xl border-fleet-border text-sm uppercase" />
                @error('plate') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Modelo') }}</label>
                <input type="text" wire:model="model" class="mt-1 w-full rounded-xl border-fleet-border text-sm" />
                @error('model') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Capacidade (kg)') }}</label>
                <input type="number" wire:model="capacity" class="mt-1 w-full rounded-xl border-fleet-border text-sm" min="1" />
                @error('capacity') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Combustível') }}</label>
                <input type="text" wire:model="fuel_type" class="mt-1 w-full rounded-xl border-fleet-border text-sm" />
                @error('fuel_type') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2 sm:col-span-2">
                <button type="submit" class="rounded-xl bg-fleet-dark px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                    {{ __('Salvar') }}
                </button>
                @if ($editingId)
                    <button type="button" wire:click="cancelEdit" class="rounded-xl border border-fleet-border px-5 py-2.5 text-sm font-semibold text-fleet-secondary hover:bg-fleet-page">
                        {{ __('Cancelar') }}
                    </button>
                @endif
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-sm">
        <table class="min-w-full divide-y divide-fleet-border text-sm">
            <thead class="bg-fleet-page text-left text-xs font-semibold uppercase text-fleet-muted">
                <tr>
                    <th class="px-4 py-3">{{ __('Placa') }}</th>
                    <th class="px-4 py-3">{{ __('Modelo') }}</th>
                    <th class="px-4 py-3">{{ __('Capacidade') }}</th>
                    <th class="px-4 py-3">{{ __('Combustível') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-fleet-border">
                @foreach ($vehicles as $vehicle)
                    <tr wire:key="v-{{ $vehicle->id }}">
                        <td class="px-4 py-3 font-medium text-fleet-ink">{{ $vehicle->plate }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $vehicle->model }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ number_format($vehicle->capacity, 0, ',', '.') }} kg</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $vehicle->fuel_type }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="startEdit({{ $vehicle->id }})" class="text-fleet-primary hover:underline">{{ __('Editar') }}</button>
                            <button type="button" wire:click="delete({{ $vehicle->id }})" wire:confirm="{{ __('Remover este veículo?') }}" class="ms-3 text-fleet-danger hover:underline">{{ __('Excluir') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="border-t border-fleet-border px-4 py-3">
            {{ $vehicles->links() }}
        </div>
    </div>
</div>
