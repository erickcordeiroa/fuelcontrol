<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('vehicles.index') }}" wire:navigate class="text-sm font-medium text-fleet-primary hover:underline">{{ __('← Voltar à lista') }}</a>
        <h1 class="mt-4 text-2xl font-bold text-fleet-ink">{{ __('Novo veículo') }}</h1>
        <p class="mt-1 text-sm text-fleet-secondary">{{ __('Preencha os dados do veículo.') }}</p>
    </div>

    <div class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
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
                <a href="{{ route('vehicles.index') }}" wire:navigate class="rounded-xl border border-fleet-border px-5 py-2.5 text-sm font-semibold text-fleet-secondary hover:bg-fleet-page">
                    {{ __('Cancelar') }}
                </a>
            </div>
        </form>
    </div>
</div>
