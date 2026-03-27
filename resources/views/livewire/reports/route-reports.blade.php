<div class="space-y-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-fleet-ink">{{ __('Relatórios de Rotas') }}</h1>
            <p class="mt-1 text-sm text-fleet-secondary">{{ __('Combustível, despesas e desempenho por viagem e veículo.') }}</p>
        </div>
        <div class="flex gap-2">
            <button type="button" disabled class="cursor-not-allowed rounded-xl border border-fleet-border bg-fleet-card px-4 py-2 text-xs font-semibold uppercase tracking-wide text-fleet-muted">
                {{ __('Export PDF') }}
            </button>
            <button type="button" disabled class="cursor-not-allowed rounded-xl border border-fleet-border bg-fleet-card px-4 py-2 text-xs font-semibold uppercase tracking-wide text-fleet-muted">
                {{ __('Export Excel') }}
            </button>
        </div>
    </div>

    <div class="rounded-2xl border border-fleet-border bg-fleet-card p-4 shadow-sm">
        <div class="grid gap-4 lg:grid-cols-5">
            <div>
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('De') }}</label>
                <input
                    type="text"
                    wire:model.live="startDateBr"
                    placeholder="dd/mm/aaaa"
                    maxlength="10"
                    autocomplete="off"
                    class="mt-1 w-full rounded-xl border-fleet-border text-sm"
                />
                @error('startDateBr') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Até') }}</label>
                <input
                    type="text"
                    wire:model.live="endDateBr"
                    placeholder="dd/mm/aaaa"
                    maxlength="10"
                    autocomplete="off"
                    class="mt-1 w-full rounded-xl border-fleet-border text-sm"
                />
                @error('endDateBr') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            @if (auth()->user()->isAdmin())
                <div>
                    <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Veículo') }}</label>
                    <select wire:model.live="filterVehicleId" class="mt-1 w-full rounded-xl border-fleet-border text-sm">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->plate }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium uppercase text-fleet-secondary">{{ __('Motorista') }}</label>
                    <select wire:model.live="filterDriverId" class="mt-1 w-full rounded-xl border-fleet-border text-sm">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="flex items-end">
                <button type="button" wire:click="applyFilters" class="w-full rounded-xl bg-fleet-dark px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                    {{ __('Filtrar') }}
                </button>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-4">
        <div class="rounded-2xl bg-fleet-dark p-5 text-white shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ __('Custo combustível') }}</p>
            <p class="mt-2 text-2xl font-bold">R$ {{ number_format($metrics['total_fuel_cost'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Outras despesas') }}</p>
            <p class="mt-2 text-2xl font-bold text-fleet-ink">R$ {{ number_format($metrics['total_other_expenses'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Total operacional') }}</p>
            <p class="mt-2 text-2xl font-bold text-fleet-ink">R$ {{ number_format($metrics['total_operational_cost'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Litros') }}</p>
            <p class="mt-2 text-2xl font-bold text-fleet-ink">{{ number_format($metrics['total_liters'], 2, ',', '.') }} L</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Consumo médio (por diário)') }}</p>
            <p class="mt-2 text-2xl font-bold text-fleet-ink">
                @if ($metrics['efficiency_km_per_liter'] !== null)
                    {{ $metrics['efficiency_km_per_liter'] }} km/L
                @else
                    —
                @endif
            </p>
            <p class="mt-1 text-xs text-fleet-muted">{{ __('Média do km/L de cada registro do diário de bordo no período') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Preço médio por KM (por diário)') }}</p>
            <p class="mt-2 text-2xl font-bold text-fleet-ink">
                @if ($metrics['cost_per_km'] !== null)
                    R$ {{ number_format($metrics['cost_per_km'], 2, ',', '.') }}
                @else
                    —
                @endif
            </p>
            <p class="mt-1 text-xs text-fleet-muted">{{ __('Média do R$/km (só combustível) de cada registro do diário') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('KM rodados (período)') }}</p>
            <p class="mt-2 text-2xl font-bold text-fleet-ink">{{ number_format($metrics['total_km'], 0, ',', '.') }} km</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-fleet-ink">{{ __('Custos diários (combustível vs. outras despesas)') }}</h3>
            <div class="mt-4 h-64" wire:ignore>
                <canvas id="reportLine"></canvas>
            </div>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-fleet-ink">{{ __('Consumo médio por veículo (km/L)') }}</h3>
            <div class="mt-4 h-64" wire:ignore>
                <canvas id="reportBars"></canvas>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-sm">
        <div class="border-b border-fleet-border px-4 py-3">
            <h3 class="text-sm font-semibold text-fleet-ink">{{ __('Detalhamento de viagens') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-fleet-border text-sm">
                <thead class="bg-fleet-page text-left text-xs font-semibold uppercase text-fleet-muted">
                    <tr>
                        <th class="px-4 py-3">{{ __('Data') }}</th>
                        <th class="px-4 py-3">{{ __('Veículo / motorista') }}</th>
                        <th class="px-4 py-3">{{ __('KM') }}</th>
                        <th class="px-4 py-3">{{ __('Litros') }}</th>
                        <th class="px-4 py-3">{{ __('R$/L') }}</th>
                        <th class="px-4 py-3">{{ __('Posto / convênio') }}</th>
                        <th class="px-4 py-3">{{ __('km/L') }}</th>
                        <th class="px-4 py-3">{{ __('R$/km (comb.)') }}</th>
                        <th class="px-4 py-3">{{ __('Total comb. (R$)') }}</th>
                        <th class="px-4 py-3">{{ __('Pedágio') }}</th>
                        <th class="px-4 py-3">{{ __('Ajudante') }}</th>
                        <th class="px-4 py-3">{{ __('Alimentação') }}</th>
                        <th class="px-4 py-3">{{ __('Total operacional') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-fleet-border">
                    @forelse ($trips as $trip)
                        @php
                            $expenseToll = $trip->expenseAmountFor(\App\Enums\ExpenseType::Toll);
                            $expenseAssistant = $trip->expenseAmountFor(\App\Enums\ExpenseType::Assistant);
                            $expenseFood = $trip->expenseAmountFor(\App\Enums\ExpenseType::Food);
                        @endphp
                        <tr wire:key="trip-{{ $trip->id }}">
                            <td class="px-4 py-3 text-fleet-secondary">{{ $trip->date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-fleet-ink">{{ $trip->vehicle?->plate }}</div>
                                <div class="text-xs text-fleet-muted">{{ $trip->driver?->name }}</div>
                            </td>
                            <td class="px-4 py-3 text-fleet-secondary">{{ number_format($trip->km_total, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-fleet-secondary">
                                @if ($trip->fuel !== null)
                                    {{ number_format((float) $trip->fuel->liters, 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-fleet-secondary">
                                @if ($trip->fuel !== null)
                                    R$ {{ number_format((float) $trip->fuel->price_per_liter, 3, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="max-w-[10rem] truncate px-4 py-3 text-fleet-secondary" title="{{ $trip->fuel?->station ?? '' }}">
                                {{ $trip->fuel?->station ? $trip->fuel->station : '—' }}
                            </td>
                            <td class="px-4 py-3 text-fleet-secondary">
                                @if ($trip->fuelEfficiencyKmPerLiter() !== null)
                                    {{ number_format($trip->fuelEfficiencyKmPerLiter(), 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-fleet-secondary">
                                @if ($trip->fuelCostPerKm() !== null)
                                    R$ {{ number_format($trip->fuelCostPerKm(), 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-sm">{{ $trip->fuelCost() > 0 ? 'R$ '.number_format($trip->fuelCost(), 2, ',', '.') : '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-sm">{{ $expenseToll > 0 ? 'R$ '.number_format($expenseToll, 2, ',', '.') : '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-sm">{{ $expenseAssistant > 0 ? 'R$ '.number_format($expenseAssistant, 2, ',', '.') : '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-sm">{{ $expenseFood > 0 ? 'R$ '.number_format($expenseFood, 2, ',', '.') : '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-fleet-ink">R$ {{ number_format($trip->operationalCost(), 2, ',', '.') }}</td>
                            <td class="px-4 py-3">
                                @if ($trip->status->value === 'completed')
                                    <span class="rounded-full bg-fleet-success/15 px-2 py-1 text-xs font-semibold uppercase text-fleet-profit">{{ __('Concluído') }}</span>
                                @else
                                    <span class="rounded-full bg-fleet-primary/15 px-2 py-1 text-xs font-semibold uppercase text-fleet-primary">{{ __('Em curso') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-4 py-8 text-center text-fleet-muted">{{ __('Nenhuma viagem no período.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-fleet-border px-4 py-3">
            {{ $trips->links() }}
        </div>
    </div>
</div>
