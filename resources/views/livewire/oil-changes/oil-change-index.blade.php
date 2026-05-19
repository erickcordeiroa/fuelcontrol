<div class="w-full space-y-8">
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="fleet-page-title">{{ __('Trocas de óleo') }}</h1>
            <p class="fleet-page-lead">{{ __('Controle de manutenção: data, marca e intervalo de troca por veículo.') }}</p>
        </div>
        <button type="button" wire:click="openCreateModal" class="fleet-btn--primary">
            <x-icons.plus class="h-5 w-5 shrink-0" />
            {{ __('Nova troca de óleo') }}
        </button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-fleet">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-fleet-border text-fleet-body">
                <thead class="fleet-table-head">
                    <tr>
                        <th class="px-4 py-3">{{ __('Data') }}</th>
                        <th class="px-4 py-3">{{ __('Veículo') }}</th>
                        <th class="px-4 py-3">{{ __('Marca') }}</th>
                        <th class="px-4 py-3">{{ __('Intervalo') }}</th>
                        <th class="px-4 py-3">{{ __('Km da troca') }}</th>
                        <th class="px-4 py-3">{{ __('Km percorridos') }}</th>
                        <th class="px-4 py-3">{{ __('Km restantes') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-fleet-border">
                    @forelse ($oilChanges as $oilChange)
                        @php
                            $computed = $oilChange->getAttribute('computed');
                            $kmRemaining = $computed['km_remaining'];
                            $daysRemaining = $computed['days_remaining'];
                            $reasons = $computed['reasons'];
                            $needsAttention = ! empty($reasons);
                            $isLatestOilChange = (bool) $oilChange->getAttribute('is_latest_oil_change_for_vehicle');
                        @endphp
                        <tr wire:key="oc-{{ $oilChange->id }}">
                            <td class="px-4 py-3 text-fleet-secondary">{{ $oilChange->date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-fleet-ink">{{ $oilChange->vehicle?->plate }}</div>
                                <div class="text-xs text-fleet-muted">{{ $oilChange->vehicle?->model }}</div>
                            </td>
                            <td class="px-4 py-3 text-fleet-secondary">{{ $oilChange->oil_brand }}</td>
                            <td class="px-4 py-3 text-fleet-secondary">{{ number_format($oilChange->interval_km, 0, ',', '.') }} km</td>
                            <td class="px-4 py-3 text-fleet-secondary">
                                @if ($computed['km_at_change'] !== null)
                                    {{ number_format($computed['km_at_change'], 0, ',', '.') }} km
                                @else
                                    <span class="text-fleet-muted">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-fleet-secondary">{{ number_format($computed['km_driven'], 0, ',', '.') }} km</td>
                            <td class="px-4 py-3 {{ $kmRemaining <= 1000 ? 'font-semibold text-fleet-danger' : 'text-fleet-secondary' }}">
                                @if ($kmRemaining <= 0)
                                    {{ __('Vencido em :n km', ['n' => number_format(abs($kmRemaining), 0, ',', '.')]) }}
                                @else
                                    {{ number_format($kmRemaining, 0, ',', '.') }} km
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if (! $isLatestOilChange)
                                    <span
                                        class="rounded-full border border-fleet-border bg-fleet-page px-2 py-1 text-xs font-semibold uppercase text-fleet-secondary"
                                        title="{{ __('Ciclo encerrado por uma troca mais recente neste veículo.') }}"
                                    >
                                        {{ __('Finalizado') }}
                                    </span>
                                @elseif ($needsAttention)
                                    <span class="rounded-full bg-fleet-danger/15 px-2 py-1 text-xs font-semibold uppercase text-fleet-danger">
                                        @if (in_array('km', $reasons, true) && in_array('time', $reasons, true))
                                            {{ __('Trocar (km + tempo)') }}
                                        @elseif (in_array('km', $reasons, true))
                                            {{ __('Trocar (km)') }}
                                        @else
                                            {{ __('Trocar (tempo)') }}
                                        @endif
                                    </span>
                                @else
                                    <span class="rounded-full bg-fleet-success/15 px-2 py-1 text-xs font-semibold uppercase text-fleet-profit">{{ __('Em dia') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <button
                                        type="button"
                                        wire:click="openEditModal({{ $oilChange->id }})"
                                        class="fleet-icon-btn"
                                        aria-label="{{ __('Editar') }}"
                                        title="{{ __('Editar') }}"
                                    >
                                        <x-icons.pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="openDeleteModal({{ $oilChange->id }})"
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
                            <td colspan="9" class="px-4 py-8 text-center text-fleet-muted">{{ __('Nenhuma troca de óleo registrada.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-fleet-border px-4 py-3">
            {{ $oilChanges->links() }}
        </div>
    </div>

    @if ($showDeleteModal)
        <x-fleet.delete-confirm-modal
            :title="__('Remover troca de óleo?')"
            :description="__('Esta ação não pode ser desfeita. O registro será excluído permanentemente.')"
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
                <div class="relative z-10 flex min-h-full items-start justify-center overflow-y-auto px-4 pb-10 pt-32 sm:px-4 sm:pb-10 sm:pt-36 lg:pt-40">
                    <div
                        class="w-full max-w-2xl rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-fleet-card"
                        onclick="event.stopPropagation()"
                        wire:key="oil-change-modal-{{ $editingId ?? 'new' }}"
                    >
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <h2 class="fleet-modal-title">
                                {{ $editingId ? __('Editar troca de óleo') : __('Nova troca de óleo') }}
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
                                <label class="fleet-label" for="oil-vehicle">{{ __('Veículo') }}</label>
                                <select id="oil-vehicle" wire:model="vehicleId" class="fleet-field">
                                    <option value="">{{ __('Selecione') }}</option>
                                    @foreach ($vehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}">{{ $vehicle->plate }} · {{ $vehicle->model }}</option>
                                    @endforeach
                                </select>
                                @error('vehicleId') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="fleet-label" for="oil-date">{{ __('Data da troca') }}</label>
                                <x-fleet.br-date-input id="oil-date" for="date" :live="false" />
                                @error('date') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="fleet-label" for="oil-km">{{ __('KM do veículo na troca') }}</label>
                                <input
                                    id="oil-km"
                                    type="number"
                                    inputmode="numeric"
                                    min="0"
                                    step="1"
                                    wire:model="km_at_change"
                                    class="fleet-field"
                                    placeholder="{{ __('Ex.: 53216') }}"
                                />
                                @error('km_at_change') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="fleet-label" for="oil-brand">{{ __('Marca do óleo') }}</label>
                                <input id="oil-brand" type="text" wire:model="oil_brand" class="fleet-field" placeholder="{{ __('Ex.: Mobil, Shell, Castrol') }}" />
                                @error('oil_brand') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="fleet-label" for="oil-interval">{{ __('Intervalo') }}</label>
                                <select id="oil-interval" wire:model="interval_km" class="fleet-field">
                                    <option value="5000">{{ __('5.000 km') }}</option>
                                    <option value="10000">{{ __('10.000 km') }}</option>
                                </select>
                                @error('interval_km') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="fleet-label" for="oil-notes">{{ __('Observações') }}</label>
                                <textarea id="oil-notes" rows="2" wire:model="notes" class="fleet-field" placeholder="{{ __('Opcional') }}"></textarea>
                                @error('notes') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
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
