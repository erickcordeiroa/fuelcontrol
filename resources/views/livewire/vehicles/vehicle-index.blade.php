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
        <a
            href="{{ route('vehicles.create') }}"
            wire:navigate
            class="inline-flex items-center justify-center rounded-xl bg-fleet-dark px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"
        >
            {{ __('Novo veículo') }}
        </a>
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
                @forelse ($vehicles as $vehicle)
                    <tr wire:key="v-{{ $vehicle->id }}">
                        <td class="px-4 py-3 font-medium text-fleet-ink">{{ $vehicle->plate }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $vehicle->model }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ number_format($vehicle->capacity, 0, ',', '.') }} kg</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $vehicle->fuel_type }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('vehicles.edit', $vehicle) }}" wire:navigate class="text-fleet-primary hover:underline">{{ __('Editar') }}</a>
                            <button
                                type="button"
                                wire:click="delete({{ $vehicle->id }})"
                                wire:confirm="{{ __('Remover este veículo?') }}"
                                class="ms-3 text-fleet-danger hover:underline"
                            >
                                {{ __('Excluir') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-fleet-muted">{{ __('Nenhum veículo cadastrado.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-fleet-border px-4 py-3">
            {{ $vehicles->links() }}
        </div>
    </div>
</div>
