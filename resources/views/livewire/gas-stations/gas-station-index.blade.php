<div class="w-full space-y-8">
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="fleet-page-title">{{ __('Postos') }}</h1>
            <p class="fleet-page-lead">{{ __('Nome, contato, endereço e preços por tipo de combustível.') }}</p>
        </div>
        <button type="button" wire:click="openCreateModal" class="fleet-btn--primary">
            <x-icons.plus class="h-5 w-5 shrink-0" />
            {{ __('Novo posto') }}
        </button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-fleet">
        <table class="min-w-full divide-y divide-fleet-border text-fleet-body">
            <thead class="fleet-table-head">
                <tr>
                    <th class="px-4 py-3">{{ __('Nome') }}</th>
                    <th class="px-4 py-3">{{ __('Telefone') }}</th>
                    <th class="px-4 py-3">{{ __('Endereço') }}</th>
                    <th class="px-4 py-3">{{ __('Combustíveis (R$/L)') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-fleet-border">
                @forelse ($gasStations as $station)
                    <tr wire:key="gs-{{ $station->id }}">
                        <td class="px-4 py-3 font-medium text-fleet-ink">{{ $station->name }}</td>
                        <td class="px-4 py-3 text-fleet-secondary">{{ $station->phone ?? '—' }}</td>
                        <td class="max-w-xl truncate px-4 py-3 text-fleet-secondary" title="{{ $station->address ?? '' }}">{{ $station->address ?? '—' }}</td>
                        <td class="max-w-md px-4 py-3 text-xs leading-relaxed text-fleet-secondary">
                            @forelse ($station->fuelOfferings as $o)
                                <span>{{ $o->fuel_type->label() }} R$ {{ number_format((float) $o->price_per_liter, 2, ',', '.') }}@if (! $loop->last)<span class="text-fleet-muted"> · </span>@endif</span>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center justify-end gap-1">
                                <button
                                    type="button"
                                    wire:click="openEditModal({{ $station->id }})"
                                    class="fleet-icon-btn"
                                    aria-label="{{ __('Editar') }}"
                                    title="{{ __('Editar') }}"
                                >
                                    <x-icons.pencil class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    wire:click="openDeleteModal({{ $station->id }})"
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
                        <td colspan="5" class="px-4 py-8 text-center text-fleet-muted">{{ __('Nenhum posto cadastrado.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-fleet-border px-4 py-3">
            {{ $gasStations->links() }}
        </div>
    </div>

    @if ($showDeleteModal)
        <x-fleet.delete-confirm-modal
            :title="__('Remover posto?')"
            :description="__('Esta ação não pode ser desfeita. O posto será excluído permanentemente.')"
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
                        wire:key="gas-station-modal-{{ $editingId ?? 'new' }}"
                    >
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <h2 class="fleet-modal-title">
                                {{ $editingId ? __('Editar posto') : __('Novo posto') }}
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
                            <div class="sm:col-span-2">
                                <label class="fleet-label" for="gas-station-name">{{ __('Nome') }}</label>
                                <input id="gas-station-name" type="text" wire:model="name" class="fleet-field" />
                                @error('name') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="fleet-label" for="gas-station-phone">{{ __('Telefone') }}</label>
                                <input
                                    id="gas-station-phone"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="tel"
                                    x-data="fleetBrPhoneField('phone')"
                                    x-bind:value="format()"
                                    x-on:keydown="onKeydown($event)"
                                    x-on:beforeinput="onBeforeInput($event)"
                                    x-on:paste="onPaste($event)"
                                    placeholder="(00) 00000-0000"
                                    class="fleet-field"
                                />
                                @error('phone') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="fleet-label" for="gas-station-address">{{ __('Endereço') }}</label>
                                <input id="gas-station-address" type="text" wire:model="address" class="fleet-field" />
                                @error('address') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <div class="flex flex-wrap items-end justify-between gap-2">
                                    <span class="fleet-label">{{ __('Combustíveis e preço por litro (R$)') }}</span>
                                    <button type="button" wire:click="addFuelOfferingRow" class="fleet-btn-text">
                                        <x-icons.plus class="h-3.5 w-3.5 shrink-0" />
                                        {{ __('Adicionar combustível') }}
                                    </button>
                                </div>
                                @error('fuel_offerings') <p class="mt-2 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                                <div class="mt-2 space-y-3">
                                    @foreach ($fuel_offerings as $offeringKey => $row)
                                        <div
                                            class="flex flex-col gap-2 rounded-xl border border-fleet-border bg-fleet-primary/[0.04] p-3 sm:flex-row sm:items-end"
                                            wire:key="fo-{{ $editingId ?? 'new' }}-{{ $offeringKey }}"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <label class="fleet-label--compact">{{ __('Combustível') }}</label>
                                                <select wire:model.live="fuel_offerings.{{ $offeringKey }}.fuel_type" class="fleet-field">
                                                    @foreach ($fuelTypeCases as $ft)
                                                        <option value="{{ $ft->value }}">{{ $ft->label() }}</option>
                                                    @endforeach
                                                </select>
                                                @error('fuel_offerings.'.$offeringKey.'.fuel_type') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="w-full sm:w-40">
                                                <label class="fleet-label--compact">{{ __('R$/L') }}</label>
                                                <input
                                                    type="text"
                                                    inputmode="numeric"
                                                    autocomplete="off"
                                                    x-data="fleetBrlMoneyField('fuel_offerings.{{ $offeringKey }}.price_per_liter', 2)"
                                                    x-bind:value="format()"
                                                    x-on:keydown="onKeydown($event)"
                                                    x-on:beforeinput="onBeforeInput($event)"
                                                    x-on:paste="onPaste($event)"
                                                    placeholder="0,00"
                                                    class="fleet-field"
                                                />
                                                @error('fuel_offerings.'.$offeringKey.'.price_per_liter') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="flex shrink-0 justify-end sm:pb-1">
                                                <button
                                                    type="button"
                                                    wire:click="removeFuelOfferingRow('{{ $offeringKey }}')"
                                                    @disabled(count($fuel_offerings) <= 1)
                                                    class="fleet-icon-btn fleet-icon-btn--danger disabled:pointer-events-none disabled:opacity-40"
                                                    aria-label="{{ __('Remover') }}"
                                                    title="{{ __('Remover') }}"
                                                >
                                                    <x-icons.trash class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
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
