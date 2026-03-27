<div class="mx-auto max-w-5xl space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-fleet-ink">{{ __('Postos') }}</h1>
            <p class="mt-1 text-sm text-fleet-secondary">{{ __('Nome, contato, endereço e valor de referência do litro.') }}</p>
        </div>
        <a
            href="{{ route('gas-stations.create') }}"
            wire:navigate
            class="inline-flex items-center justify-center rounded-xl bg-fleet-dark px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"
        >
            {{ __('Novo posto') }}
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-sm">
        <table class="min-w-full divide-y divide-fleet-border text-sm">
            <thead class="bg-fleet-page text-left text-xs font-semibold uppercase text-fleet-muted">
                <tr>
                    <th class="px-4 py-3">{{ __('Nome') }}</th>
                    <th class="px-4 py-3">{{ __('Telefone') }}</th>
                    <th class="px-4 py-3">{{ __('Endereço') }}</th>
                    <th class="px-4 py-3">{{ __('R$/L') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-fleet-border">
                @forelse ($gasStations as $station)
                    <tr wire:key="gs-{{ $station->id }}">
                        <td class="px-4 py-3 font-medium text-fleet-ink">{{ $station->name }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $station->phone ?? '—' }}</td>
                        <td class="max-w-xs truncate px-4 py-3 text-fleet-secondary" title="{{ $station->address ?? '' }}">{{ $station->address ?? '—' }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">R$ {{ number_format((float) $station->price_per_liter, 4, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('gas-stations.edit', $station) }}" wire:navigate class="text-fleet-primary hover:underline">{{ __('Editar') }}</a>
                            <button
                                type="button"
                                wire:click="delete({{ $station->id }})"
                                wire:confirm="{{ __('Remover este posto?') }}"
                                class="ms-3 text-fleet-danger hover:underline"
                            >
                                {{ __('Excluir') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-fleet-muted">{{ __('Nenhum posto cadastrado.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-fleet-border px-4 py-3">
            {{ $gasStations->links() }}
        </div>
    </div>
</div>
