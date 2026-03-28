<div class="w-full space-y-8">
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif

    <div>
        <h1 class="text-2xl font-bold text-fleet-ink">{{ __('Diário de Bordo') }}</h1>
        <p class="mt-1 text-sm text-fleet-secondary">{{ __('Registre rota, abastecimento e despesas em um único fluxo.') }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Informações de rota') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Data') }}</label>
                        <input type="date" wire:model.live="date" class="mt-1 w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20" />
                        @error('date') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Placa do veículo') }}</label>
                        <select wire:model.live="vehicle_id" class="mt-1 w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20">
                            <option value="">{{ __('Selecione') }}</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}">{{ $vehicle->plate }} — {{ $vehicle->model }}</option>
                            @endforeach
                        </select>
                        @error('vehicle_id') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    @if (auth()->user()->isAdmin())
                        <div class="sm:col-span-2">
                            <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Motorista') }}</label>
                            <select wire:model.live="driver_id" class="mt-1 w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20">
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
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('KM inicial') }}</label>
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
                            class="mt-1 w-full rounded-xl border-fleet-border text-sm tabular-nums focus:border-fleet-primary focus:ring-fleet-primary/20"
                        />
                        @error('km_start') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('KM final') }}</label>
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
                            class="mt-1 w-full rounded-xl border-fleet-border text-sm tabular-nums focus:border-fleet-primary focus:ring-fleet-primary/20"
                        />
                        @error('km_end') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Abastecimento') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Posto cadastrado') }}</label>
                        <select wire:model.live="gas_station_id" class="mt-1 w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20">
                            <option value="">{{ __('Selecione ou informe manualmente abaixo') }}</option>
                            @foreach ($gasStations as $gs)
                                <option value="{{ $gs->id }}">{{ $gs->name }} @if ((float) $gs->price_per_liter > 0) — R$ {{ number_format((float) $gs->price_per_liter, 4, ',', '.') }}/L @endif</option>
                            @endforeach
                        </select>
                        @error('gas_station_id') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Litros total') }}</label>
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
                            class="mt-1 w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20"
                        />
                        @error('liters') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Valor por litro (R$)') }}</label>
                        <input
                            type="text"
                            inputmode="decimal"
                            wire:model.live="price_per_liter"
                            placeholder="0,0000"
                            @if ($gas_station_id)
                                readonly
                            @endif
                            @class([
                                'mt-1 w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20',
                                'cursor-not-allowed bg-fleet-page/60 text-fleet-secondary' => $gas_station_id,
                            ])
                        />
                        @if ($gas_station_id)
                            <p class="mt-1 text-xs text-fleet-muted">{{ __('Preço do posto selecionado; Valor deve ser alterado no menu Postos.') }}</p>
                        @endif
                        @error('price_per_liter') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Posto / Convênio (nome ou observação)') }}</label>
                        <input type="text" wire:model.live="station" class="mt-1 w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20" />
                        @error('station') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Outras despesas') }}</h2>
                <p class="mt-1 text-xs text-fleet-muted">{{ __('Pedágio, ajudante e alimentação (opcional).') }}</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Pedágio (R$)') }}</label>
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
                            class="mt-1 w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20"
                        />
                        @error('toll') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Ajudante (R$)') }}</label>
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
                            class="mt-1 w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20"
                        />
                        @error('assistant') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Alimentação (R$)') }}</label>
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
                            class="mt-1 w-full rounded-xl border-fleet-border text-sm focus:border-fleet-primary focus:ring-fleet-primary/20"
                        />
                        @error('food') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl bg-fleet-dark p-6 text-white shadow-lg">
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
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-fleet-dark px-4 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="save">{{ __('Finalizar registro') }}</span>
                <span wire:loading wire:target="save">{{ __('Salvando…') }}</span>
            </button>

            <div class="rounded-2xl border border-fleet-border bg-fleet-ink p-4 text-white shadow-inner">
                <p class="text-xs font-medium text-white/70">{{ __('Mapa (MVP)') }}</p>
                <p class="mt-2 text-sm text-white/90">{{ __('Pré-visualização estática — integração GPS fora do escopo do MVP.') }}</p>
            </div>
        </div>
    </div>
</div>
