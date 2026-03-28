<div class="w-full space-y-8">
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
        <button
            type="button"
            wire:click="openCreateModal"
            class="inline-flex items-center justify-center rounded-xl bg-fleet-dark px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"
        >
            {{ __('Novo veículo') }}
        </button>
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
                        <td class="max-w-xl truncate px-4 py-3 text-fleet-secondary" title="{{ $vehicle->model }}">{{ $vehicle->model }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ number_format($vehicle->capacity, 0, ',', '.') }} kg</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $vehicle->fuel_type }}</td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                wire:click="openEditModal({{ $vehicle->id }})"
                                class="text-fleet-primary hover:underline"
                            >
                                {{ __('Editar') }}
                            </button>
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
                        wire:key="vehicle-modal-{{ $editingId ?? 'new' }}"
                    >
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-fleet-ink">
                            {{ $editingId ? __('Editar veículo') : __('Novo veículo') }}
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
                    <div>
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Placa') }}</label>
                        <input
                            type="text"
                            inputmode="text"
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            x-data="fleetBrPlateField('plate')"
                            x-bind:value="format()"
                            x-on:keydown="onKeydown($event)"
                            x-on:beforeinput="onBeforeInput($event)"
                            x-on:paste="onPaste($event)"
                            placeholder="ABC-1234"
                            class="mt-1 w-full rounded-xl border-fleet-border font-mono text-sm uppercase focus:border-fleet-primary focus:ring-fleet-primary/20"
                        />
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
