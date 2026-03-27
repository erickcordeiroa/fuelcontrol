<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('drivers.index') }}" wire:navigate class="text-sm font-medium text-fleet-primary hover:underline">{{ __('← Voltar à lista') }}</a>
        <h1 class="mt-4 text-2xl font-bold text-fleet-ink">{{ __('Novo motorista') }}</h1>
        <p class="mt-1 text-sm text-fleet-secondary">{{ __('CNH, contato e vínculo opcional com usuário.') }}</p>
    </div>

    <div class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Nome') }}</label>
                <input type="text" wire:model="name" class="mt-1 w-full rounded-xl border-fleet-border text-sm" />
                @error('name') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('CNH') }}</label>
                <input type="text" wire:model="license_number" class="mt-1 w-full rounded-xl border-fleet-border text-sm" />
                @error('license_number') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Telefone') }}</label>
                <input type="text" wire:model="phone" class="mt-1 w-full rounded-xl border-fleet-border text-sm" />
                @error('phone') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Usuário (opcional)') }}</label>
                <select wire:model="linked_user_id" class="mt-1 w-full rounded-xl border-fleet-border text-sm">
                    <option value="">{{ __('Sem vínculo') }}</option>
                    @foreach ($linkableUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} — {{ $u->email }}</option>
                    @endforeach
                </select>
                @error('linked_user_id') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2 sm:col-span-2">
                <button type="submit" class="rounded-xl bg-fleet-dark px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                    {{ __('Salvar') }}
                </button>
                <a href="{{ route('drivers.index') }}" wire:navigate class="rounded-xl border border-fleet-border px-5 py-2.5 text-sm font-semibold text-fleet-secondary hover:bg-fleet-page">
                    {{ __('Cancelar') }}
                </a>
            </div>
        </form>
    </div>
</div>
