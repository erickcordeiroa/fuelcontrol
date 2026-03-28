<div class="w-full space-y-8">
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="fleet-page-title">{{ __('Veículos') }}</h1>
            <p class="fleet-page-lead">{{ __('Cadastro de placas e capacidade.') }}</p>
        </div>
        <button type="button" wire:click="openCreateModal" class="fleet-btn--primary">
            <x-icons.plus class="h-5 w-5 shrink-0" />
            {{ __('Novo veículo') }}
        </button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-fleet">
        <table class="min-w-full divide-y divide-fleet-border text-fleet-body">
            <thead class="fleet-table-head">
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
                            <div class="inline-flex items-center justify-end gap-1">
                                <button
                                    type="button"
                                    wire:click="openEditModal({{ $vehicle->id }})"
                                    class="fleet-icon-btn"
                                    aria-label="{{ __('Editar') }}"
                                    title="{{ __('Editar') }}"
                                >
                                    <x-icons.pencil class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    wire:click="openDeleteModal({{ $vehicle->id }})"
                                    class="fleet-icon-btn fleet-icon-btn--danger"
                                    aria-label="{{ __('Excluir') }}"
                                    title="{{ __('Excluir') }}"
                                >
                                    <x-icons.trash class="h-4 w-4" />
                                </button>
                            </div>
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

    @if ($showDeleteModal)
        <x-fleet.delete-confirm-modal
            :title="__('Remover veículo?')"
            :description="__('Esta ação não pode ser desfeita. O veículo será excluído permanentemente.')"
        />
    @endif

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
                        class="w-full max-w-2xl rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-fleet-card"
                        onclick="event.stopPropagation()"
                        wire:key="vehicle-modal-{{ $editingId ?? 'new' }}"
                    >
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <h2 class="fleet-modal-title">
                                {{ $editingId ? __('Editar veículo') : __('Novo veículo') }}
                            </h2>
                            <button
                                type="button"
                                wire:click="closeModal"
                                class="fleet-icon-btn fleet-icon-btn--ghost"
                                aria-label="{{ __('Fechar') }}"
                            >
                                <x-icons.x-mark class="h-5 w-5" />
                            </button>
                        </div>

                        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="fleet-label" for="vehicle-plate">{{ __('Placa') }}</label>
                                <input
                                    id="vehicle-plate"
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
                                    class="fleet-field font-mono uppercase"
                                />
                                @error('plate') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="fleet-label" for="vehicle-model">{{ __('Modelo') }}</label>
                                <input id="vehicle-model" type="text" wire:model="model" class="fleet-field" />
                                @error('model') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="fleet-label" for="vehicle-capacity">{{ __('Capacidade (kg)') }}</label>
                                <input id="vehicle-capacity" type="number" wire:model="capacity" class="fleet-field" min="1" />
                                @error('capacity') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="fleet-label" for="vehicle-fuel">{{ __('Combustível') }}</label>
                                <input id="vehicle-fuel" type="text" wire:model="fuel_type" class="fleet-field" />
                                @error('fuel_type') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex flex-wrap gap-2 sm:col-span-2">
                                <button type="submit" class="fleet-btn--primary fleet-btn--lg">{{ __('Salvar') }}</button>
                                <button type="button" wire:click="closeModal" class="fleet-btn--muted fleet-btn--lg">{{ __('Cancelar') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
