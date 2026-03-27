<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('gas-stations.index') }}" wire:navigate class="text-sm font-medium text-fleet-primary hover:underline">{{ __('← Voltar à lista') }}</a>
        <h1 class="mt-4 text-2xl font-bold text-fleet-ink">{{ __('Novo posto') }}</h1>
    </div>

    <div class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Nome') }}</label>
                <input type="text" wire:model="name" class="mt-1 w-full rounded-xl border-fleet-border text-sm" />
                @error('name') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Telefone') }}</label>
                <input type="text" wire:model="phone" class="mt-1 w-full rounded-xl border-fleet-border text-sm" />
                @error('phone') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Valor do litro (R$)') }}</label>
                <input type="text" inputmode="decimal" wire:model="price_per_liter" class="mt-1 w-full rounded-xl border-fleet-border text-sm" placeholder="0,0000" />
                @error('price_per_liter') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Endereço') }}</label>
                <input type="text" wire:model="address" class="mt-1 w-full rounded-xl border-fleet-border text-sm" />
                @error('address') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2 sm:col-span-2">
                <button type="submit" class="rounded-xl bg-fleet-dark px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                    {{ __('Salvar') }}
                </button>
                <a href="{{ route('gas-stations.index') }}" wire:navigate class="rounded-xl border border-fleet-border px-5 py-2.5 text-sm font-semibold text-fleet-secondary hover:bg-fleet-page">
                    {{ __('Cancelar') }}
                </a>
            </div>
        </form>
    </div>
</div>
