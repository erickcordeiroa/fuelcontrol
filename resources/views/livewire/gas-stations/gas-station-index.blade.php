<div class="w-full space-y-8">
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
        <button
            type="button"
            wire:click="openCreateModal"
            class="inline-flex items-center justify-center rounded-xl bg-fleet-dark px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"
        >
            {{ __('Novo posto') }}
        </button>
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
                        <td class="max-w-xl truncate px-4 py-3 text-fleet-secondary" title="{{ $station->address ?? '' }}">{{ $station->address ?? '—' }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">R$ {{ number_format((float) $station->price_per_liter, 4, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                wire:click="openEditModal({{ $station->id }})"
                                class="text-fleet-primary hover:underline"
                            >
                                {{ __('Editar') }}
                            </button>
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

    @if ($showModal)
        @teleport('body')
            <div
                class="fixed inset-0 z-[60]"
                role="dialog"
                aria-modal="true"
                x-data
                x-on:keydown.escape.window="$wire.closeModal()"
            >
                <div class="absolute inset-0 bg-fleet-ink/50 backdrop-blur-[1px]" wire:click="closeModal"></div>
                <div
                    class="relative z-10 flex min-h-full items-start justify-center overflow-y-auto px-4 pb-10 pt-32 sm:px-4 sm:pb-10 sm:pt-36 lg:pt-40"
                >
                    <div
                        class="w-full max-w-2xl rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-xl"
                        wire:click.stop
                        wire:key="gas-station-modal-{{ $editingId ?? 'new' }}"
                    >
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-fleet-ink">
                            {{ $editingId ? __('Editar posto') : __('Novo posto') }}
                        </h2>
                    </div>
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="rounded-lg p-1.5 text-fleet-muted hover:bg-fleet-page hover:text-fleet-ink"
                        aria-label="{{ __('Fechar') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Nome') }}</label>
                        <input type="text" wire:model="name" class="mt-1 w-full rounded-xl border-fleet-border text-sm" />
                        @error('name') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Telefone') }}</label>
                        <input
                            type="text"
                            inputmode="numeric"
                            autocomplete="tel"
                            x-data="fleetBrPhoneField('phone')"
                            x-bind:value="format()"
                            x-on:keydown="onKeydown($event)"
                            x-on:beforeinput="onBeforeInput($event)"
                            x-on:paste="onPaste($event)"
                            placeholder="(00) 00000-0000"
                            class="mt-1 w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20"
                        />
                        @error('phone') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Valor do litro (R$)') }}</label>
                        <input
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            x-data="fleetBrlMoneyField('price_per_liter', 4)"
                            x-bind:value="format()"
                            x-on:keydown="onKeydown($event)"
                            x-on:beforeinput="onBeforeInput($event)"
                            x-on:paste="onPaste($event)"
                            placeholder="0,0000"
                            class="mt-1 w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20"
                        />
                        @error('price_per_liter') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Endereço') }}</label>
                        <input type="text" wire:model="address" class="mt-1 w-full rounded-xl border-fleet-border text-sm" />
                        @error('address') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-wrap gap-2 sm:col-span-2">
                        <button type="submit" class="rounded-xl bg-fleet-dark px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                            {{ __('Salvar') }}
                        </button>
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="rounded-xl border border-fleet-border px-5 py-2.5 text-sm font-semibold text-fleet-secondary hover:bg-fleet-page"
                        >
                            {{ __('Cancelar') }}
                        </button>
                    </div>
                </form>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
