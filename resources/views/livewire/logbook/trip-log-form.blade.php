<div class="w-full space-y-8">
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif

    <div>
        <h1 class="fleet-page-title">
            @if ($editingTripId)
                {{ __('Editar registro do diário') }}
            @else
                {{ __('Diário de Bordo') }}
            @endif
        </h1>
        <p class="fleet-page-lead">
            @if ($editingTripId)
                {{ __('Ajuste os dados da viagem. Ao salvar, as alterações ficam registradas no histórico do relatório.') }}
            @else
                {{ __('Registre rota, abastecimento e despesas em um único fluxo.') }}
            @endif
        </p>
        @if ($editingTripId)
            <p class="mt-2">
                <a href="{{ route('reports') }}" wire:navigate class="text-sm font-medium text-fleet-primary hover:underline">
                    {{ __('Voltar aos relatórios') }}
                </a>
            </p>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="fleet-panel">
                <h2 class="fleet-section-title">{{ __('Informações de rota') }}</h2>
                <div class="mt-4 space-y-4">
                    <div class="grid min-w-0 grid-cols-2 gap-4">
                        <div class="min-w-0">
                            <label class="fleet-label">{{ __('Data') }}</label>
                            <input type="date" wire:model.live="date" class="fleet-field w-full min-w-0" />
                            @error('date') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="min-w-0">
                            <label class="fleet-label">{{ __('Hora do lançamento') }}</label>
                            <input type="time" wire:model.live="trip_time" step="60" class="fleet-field w-full min-w-0" />
                            @error('trip_time') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="fleet-label">{{ __('Placa do veículo') }}</label>
                        <select wire:model.live="vehicle_id" class="fleet-field">
                            <option value="">{{ __('Selecione') }}</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}">{{ $vehicle->plate }} — {{ $vehicle->model }}</option>
                            @endforeach
                        </select>
                        @error('vehicle_id') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                    @if (auth()->user()->isAdmin())
                        <div class="sm:col-span-2">
                            <label class="fleet-label">{{ __('Motorista') }}</label>
                            <select wire:model.live="driver_id" class="fleet-field">
                                <option value="">{{ __('Selecione') }}</option>
                                @foreach ($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                                @endforeach
                            </select>
                            @error('driver_id') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <input type="hidden" wire:model="driver_id" />
                    @endif
                    <div>
                        <label class="fleet-label">{{ __('KM inicial') }}</label>
                        <input
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            x-data="fleetKmField('km_start')"
                            x-bind:value="format()"
                            x-on:keydown="onKeydown($event)"
                            x-on:beforeinput="onBeforeInput($event)"
                            x-on:paste="onPaste($event)"
                            placeholder="0"
                            class="fleet-field tabular-nums"
                        />
                        @error('km_start') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="fleet-label">{{ __('KM final') }}</label>
                        <input
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            x-data="fleetKmField('km_end')"
                            x-bind:value="format()"
                            x-on:keydown="onKeydown($event)"
                            x-on:beforeinput="onBeforeInput($event)"
                            x-on:paste="onPaste($event)"
                            placeholder="0"
                            class="fleet-field tabular-nums"
                        />
                        @error('km_end') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="fleet-label">{{ __('Observações') }}</label>
                        <textarea
                            wire:model.live="notes"
                            rows="3"
                            class="fleet-field min-h-[5rem] resize-y"
                            placeholder="{{ __('Ex.: ajudantes João e Maria; rota Centro — bairro X.') }}"
                        ></textarea>
                        <p class="mt-1 text-xs text-fleet-muted">{{ __('Use para nomes de ajudantes e rota ou destino da viagem.') }}</p>
                        @error('notes') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    </div>
                </div>
            </section>

            <section class="fleet-panel">
                <h2 class="fleet-section-title">{{ __('Abastecimento') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="fleet-label">{{ __('Posto cadastrado') }}</label>
                        <select wire:model.live="gas_station_id" class="fleet-field">
                            <option value="">{{ __('Nenhum — abastecimento sem posto cadastrado') }}</option>
                            @foreach ($gasStations as $gs)
                                <option value="{{ $gs->id }}">{{ $gs->name }}</option>
                            @endforeach
                        </select>
                        @error('gas_station_id') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    @if ($gas_station_id)
                        @php
                            $selectedGasStation = $gasStations->firstWhere('id', $gas_station_id);
                        @endphp
                        <div class="sm:col-span-2">
                            <label class="fleet-label">{{ __('Combustível no posto') }}</label>
                            <select wire:model.live="gas_station_fuel_offering_id" class="fleet-field">
                                <option value="">{{ __('Selecione o combustível') }}</option>
                                @foreach ($selectedGasStation?->fuelOfferings ?? [] as $offering)
                                    <option value="{{ $offering->id }}">
                                        {{ $offering->fuel_type->label() }}
                                        — R$ {{ number_format((float) $offering->price_per_liter, 2, ',', '.') }}/L
                                    </option>
                                @endforeach
                            </select>
                            @error('gas_station_fuel_offering_id') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                            @error('fuel_type') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div>
                        <label class="fleet-label">{{ __('Litros total') }}</label>
                        <input
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            x-data="fleetBrlMoneyField('liters', 2)"
                            x-bind:value="format()"
                            x-on:keydown="onKeydown($event)"
                            x-on:beforeinput="onBeforeInput($event)"
                            x-on:paste="onPaste($event)"
                            placeholder="0,00"
                            class="fleet-field"
                        />
                        @error('liters') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    @if ($gas_station_id)
                        <div>
                            <label class="fleet-label">{{ __('Valor por litro (R$)') }}</label>
                            <input
                                type="text"
                                inputmode="decimal"
                                wire:model.live="price_per_liter"
                                placeholder="0,00"
                                readonly
                                @class([
                                    'fleet-field',
                                    'cursor-not-allowed bg-fleet-page/60 text-fleet-secondary',
                                ])
                            />
                            <p class="mt-1 text-xs text-fleet-muted">
                                @if ($gas_station_fuel_offering_id)
                                    {{ __('Preço conforme cadastro do posto; altere em Postos se necessário.') }}
                                @else
                                    {{ __('Selecione o combustível para carregar o preço cadastrado.') }}
                                @endif
                            </p>
                            @error('price_per_liter') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <label class="fleet-label">{{ __('Valor por litro (R$)') }}</label>
                            <input
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                x-data="fleetBrlMoneyField('price_per_liter', 2)"
                                x-bind:value="format()"
                                x-on:keydown="onKeydown($event)"
                                x-on:beforeinput="onBeforeInput($event)"
                                x-on:paste="onPaste($event)"
                                placeholder="0,00"
                                class="fleet-field"
                            />
                            @error('price_per_liter') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="fleet-label">{{ __('Posto / Convênio (nome ou observação)') }}</label>
                            <input type="text" wire:model.live="station" class="fleet-field" />
                            @error('station') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
            </section>

            <section class="fleet-panel">
                <h2 class="fleet-section-title">{{ __('Outras despesas') }}</h2>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-fleet-border bg-fleet-page/40 p-3 sm:max-w-xl">
                        <input
                            type="checkbox"
                            wire:model.live="is_daily_operation"
                            class="mt-0.5 h-4 w-4 shrink-0 rounded border-fleet-border text-fleet-primary focus:ring-fleet-primary/30"
                        />
                        <span>
                            <span class="block text-sm font-medium text-fleet-ink">{{ __('Operação diária') }}</span>
                            <span class="mt-0.5 block text-xs text-fleet-muted">
                                {{ __('Marque se este registro inclui pedágio, ajudante e alimentação. Desmarcado, esses valores ficam zerados.') }}
                            </span>
                        </span>
                    </label>
                </div>
                @if ($is_daily_operation)
                    <p class="mt-3 text-xs text-fleet-muted">{{ __('Informe os valores ou deixe em zero quando não houver despesa.') }}</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="fleet-label">{{ __('Pedágio (R$)') }}</label>
                            <input
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                x-data="fleetBrlMoneyField('toll', 2)"
                                x-bind:value="format()"
                                x-on:keydown="onKeydown($event)"
                                x-on:beforeinput="onBeforeInput($event)"
                                x-on:paste="onPaste($event)"
                                placeholder="0,00"
                                class="fleet-field"
                            />
                            @error('toll') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="fleet-label">{{ __('Ajudante (R$)') }}</label>
                            <input
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                x-data="fleetBrlMoneyField('assistant', 2)"
                                x-bind:value="format()"
                                x-on:keydown="onKeydown($event)"
                                x-on:beforeinput="onBeforeInput($event)"
                                x-on:paste="onPaste($event)"
                                placeholder="0,00"
                                class="fleet-field"
                            />
                            @error('assistant') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="fleet-label">{{ __('Alimentação (R$)') }}</label>
                            <input
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                x-data="fleetBrlMoneyField('food', 2)"
                                x-bind:value="format()"
                                x-on:keydown="onKeydown($event)"
                                x-on:beforeinput="onBeforeInput($event)"
                                x-on:paste="onPaste($event)"
                                placeholder="0,00"
                                class="fleet-field"
                            />
                            @error('food') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            <div class="fleet-panel--inverted shadow-fleet-card">
                <p class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ __('Resumo da viagem') }}</p>
                <p class="mt-4 text-3xl font-bold">
                    @if ($this->preview['km_total'] !== null)
                        {{ number_format($this->preview['km_total'], 0, ',', '.') }} km
                    @else
                        —
                    @endif
                </p>
                <p class="mt-2 text-sm text-white/80">{{ __('Consumo médio') }}:
                    @if ($this->preview['efficiency_km_per_liter'] !== null)
                        {{ $this->preview['efficiency_km_per_liter'] }} km/L
                    @else
                        —
                    @endif
                </p>
                <p class="text-sm text-white/80">{{ __('Custo por KM') }}:
                    @if ($this->preview['cost_per_km'] !== null)
                        R$ {{ number_format($this->preview['cost_per_km'], 2, ',', '.') }}
                    @else
                        —
                    @endif
                </p>
                @if ($this->preview['km_total'] !== null)
                    <p class="mt-3 border-t border-white/10 pt-3 text-sm text-white/85">{{ __('Custo combustível') }}: R$ {{ number_format($this->preview['fuel_cost'], 2, ',', '.') }}</p>
                    <p class="text-sm text-white/85">{{ __('Outras despesas') }}: R$ {{ number_format($this->preview['total_expenses'], 2, ',', '.') }}</p>
                    <p class="text-sm font-semibold text-white">{{ __('Total operacional') }}: R$ {{ number_format($this->preview['fuel_cost'] + $this->preview['total_expenses'], 2, ',', '.') }}</p>
                @endif
            </div>

            <button
                type="button"
                wire:click="save"
                wire:loading.attr="disabled"
                class="fleet-btn--primary fleet-btn--block fleet-btn--lg disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="save" class="inline-flex items-center justify-center gap-2">
                    <x-icons.check class="h-5 w-5 shrink-0" />
                    @if ($editingTripId)
                        {{ __('Salvar alterações') }}
                    @else
                        {{ __('Finalizar registro') }}
                    @endif
                </span>
                <span wire:loading wire:target="save">{{ __('Salvando…') }}</span>
            </button>

            <div class="rounded-2xl border border-fleet-border bg-fleet-ink p-4 text-white shadow-inner">
                <p class="text-xs font-medium text-white/70">{{ __('Mapa (MVP)') }}</p>
                <p class="mt-2 text-sm text-white/90">{{ __('Pré-visualização estática — integração GPS fora do escopo do MVP.') }}</p>
            </div>
        </div>
    </div>
</div>
