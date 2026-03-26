<div class="mx-auto max-w-5xl space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-fleet-ink">{{ __('Motoristas') }}</h1>
            <p class="mt-1 text-sm text-fleet-secondary">{{ __('CNH, contato e vínculo opcional com usuário.') }}</p>
        </div>
        <a href="{{ route('assets.vehicles') }}" wire:navigate class="rounded-xl border border-fleet-border bg-fleet-card px-4 py-2 text-sm font-semibold text-fleet-ink hover:bg-fleet-page">
            {{ __('Veículos') }}
        </a>
    </div>

    <div class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">
            {{ $editingId ? __('Editar motorista') : __('Novo motorista') }}
        </h2>
        <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
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
                <select wire:model="user_id" class="mt-1 w-full rounded-xl border-fleet-border text-sm">
                    <option value="">{{ __('Sem vínculo') }}</option>
                    @foreach ($linkableUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} — {{ $u->email }}</option>
                    @endforeach
                </select>
                @error('user_id') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
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
                    <th class="px-4 py-3">{{ __('Nome') }}</th>
                    <th class="px-4 py-3">{{ __('CNH') }}</th>
                    <th class="px-4 py-3">{{ __('Usuário') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-fleet-border">
                @foreach ($drivers as $driver)
                    <tr wire:key="d-{{ $driver->id }}">
                        <td class="px-4 py-3 font-medium text-fleet-ink">{{ $driver->name }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $driver->license_number }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $driver->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="startEdit({{ $driver->id }})" class="text-fleet-primary hover:underline">{{ __('Editar') }}</button>
                            <button type="button" wire:click="delete({{ $driver->id }})" wire:confirm="{{ __('Remover este motorista?') }}" class="ms-3 text-fleet-danger hover:underline">{{ __('Excluir') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="border-t border-fleet-border px-4 py-3">
            {{ $drivers->links() }}
        </div>
    </div>
</div>
