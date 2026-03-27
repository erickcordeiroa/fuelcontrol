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
        <a
            href="{{ route('drivers.create') }}"
            wire:navigate
            class="inline-flex items-center justify-center rounded-xl bg-fleet-dark px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"
        >
            {{ __('Novo motorista') }}
        </a>
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
                @forelse ($drivers as $driver)
                    <tr wire:key="d-{{ $driver->id }}">
                        <td class="px-4 py-3 font-medium text-fleet-ink">{{ $driver->name }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $driver->license_number }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $driver->linkedUser?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('drivers.edit', $driver) }}" wire:navigate class="text-fleet-primary hover:underline">{{ __('Editar') }}</a>
                            <button
                                type="button"
                                wire:click="delete({{ $driver->id }})"
                                wire:confirm="{{ __('Remover este motorista?') }}"
                                class="ms-3 text-fleet-danger hover:underline"
                            >
                                {{ __('Excluir') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-fleet-muted">{{ __('Nenhum motorista cadastrado.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-fleet-border px-4 py-3">
            {{ $drivers->links() }}
        </div>
    </div>
</div>
