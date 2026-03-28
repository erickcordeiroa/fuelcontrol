<div class="space-y-8">
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="fleet-page-title">{{ __('Relatórios de Rotas') }}</h1>
            <p class="fleet-page-lead">{{ __('Combustível, despesas e desempenho por viagem e veículo.') }}</p>
        </div>
        <div class="flex gap-2">
            <button type="button" disabled class="fleet-btn--outline fleet-btn--sm cursor-not-allowed opacity-50">
                {{ __('Export PDF') }}
            </button>
            <button type="button" disabled class="fleet-btn--outline fleet-btn--sm cursor-not-allowed opacity-50">
                {{ __('Export Excel') }}
            </button>
        </div>
    </div>

    <div class="rounded-2xl border border-fleet-border bg-fleet-card p-4 shadow-fleet">
        <div class="grid gap-4 lg:grid-cols-5">
            <div>
                <label class="fleet-label">{{ __('De') }}</label>
                <input
                    type="text"
                    wire:model.live="startDateBr"
                    placeholder="dd/mm/aaaa"
                    maxlength="10"
                    autocomplete="off"
                    class="fleet-field"
                />
                @error('startDateBr') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="fleet-label">{{ __('Até') }}</label>
                <input
                    type="text"
                    wire:model.live="endDateBr"
                    placeholder="dd/mm/aaaa"
                    maxlength="10"
                    autocomplete="off"
                    class="fleet-field"
                />
                @error('endDateBr') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            @if (auth()->user()->isAdmin())
                <div>
                    <label class="fleet-label">{{ __('Veículo') }}</label>
                    <select wire:model.live="filterVehicleId" class="fleet-field">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->plate }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fleet-label">{{ __('Motorista') }}</label>
                    <select wire:model.live="filterDriverId" class="fleet-field">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="flex items-end">
                <button type="button" wire:click="applyFilters" class="fleet-btn--primary fleet-btn--block">
                    {{ __('Filtrar') }}
                </button>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-4">
        <div class="fleet-panel--inverted !p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ __('Custo combustível') }}</p>
            <p class="mt-2 text-2xl font-bold">R$ {{ number_format($metrics['total_fuel_cost'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-fleet">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Outras despesas') }}</p>
            <p class="mt-2 text-2xl font-bold text-fleet-ink">R$ {{ number_format($metrics['total_other_expenses'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-fleet">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Total operacional') }}</p>
            <p class="mt-2 text-2xl font-bold text-fleet-ink">R$ {{ number_format($metrics['total_operational_cost'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-fleet">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Litros') }}</p>
            <p class="mt-2 text-2xl font-bold text-fleet-ink">{{ number_format($metrics['total_liters'], 2, ',', '.') }} L</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-fleet">
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
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-fleet">
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
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-fleet">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('KM rodados (período)') }}</p>
            <p class="mt-2 text-2xl font-bold text-fleet-ink">{{ number_format($metrics['total_km'], 0, ',', '.') }} km</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-4 shadow-fleet">
            <h3 class="text-sm font-semibold text-fleet-ink">{{ __('Custos diários (combustível vs. outras despesas)') }}</h3>
            <div class="mt-4 h-64" wire:ignore>
                <canvas id="reportLine"></canvas>
            </div>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-4 shadow-fleet">
            <h3 class="text-sm font-semibold text-fleet-ink">{{ __('Consumo médio por veículo (km/L)') }}</h3>
            <div class="mt-4 h-64" wire:ignore>
                <canvas id="reportBars"></canvas>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-fleet-border bg-fleet-card shadow-fleet">
        <div class="rounded-t-2xl border-b border-fleet-border px-4 py-3">
            <h3 class="text-sm font-semibold text-fleet-ink">{{ __('Detalhamento de viagens') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-fleet-border text-fleet-body">
                <thead class="fleet-table-head">
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
                        <th class="px-4 py-3 text-right">{{ __('Ações') }}</th>
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
                                    {{ $trip->fuel->fuel_type->label() }} · R$ {{ number_format((float) $trip->fuel->price_per_liter, 2, ',', '.') }}
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
                            <td class="px-4 py-3 text-right">
                                <x-fleet.trip-row-actions :trip="$trip" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="px-4 py-8 text-center text-fleet-muted">{{ __('Nenhuma viagem no período.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="rounded-b-2xl border-t border-fleet-border px-4 py-3">
            {{ $trips->links() }}
        </div>
    </div>

    @if ($historyTripId !== null)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-fleet-ink/50 p-4"
            wire:click="closeTripHistory"
            wire:key="trip-history-modal"
        >
            <div
                class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-fleet"
                wire:click.stop
                role="dialog"
                aria-modal="true"
                aria-labelledby="trip-history-title"
            >
                <div class="flex items-start justify-between gap-4">
                    <h3 id="trip-history-title" class="text-lg font-semibold text-fleet-ink">
                        {{ __('Histórico de alterações do diário') }}
                    </h3>
                    <button
                        type="button"
                        wire:click="closeTripHistory"
                        class="rounded-lg p-1 text-fleet-muted hover:bg-fleet-page hover:text-fleet-ink"
                        aria-label="{{ __('Fechar') }}"
                    >
                        <span class="sr-only">{{ __('Fechar') }}</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <p class="mt-1 text-sm text-fleet-muted">{{ __('Registro #:id', ['id' => $historyTripId]) }}</p>

                <div class="mt-6 space-y-6">
                    @forelse ($tripChangeLogs as $log)
                        <div class="rounded-xl border border-fleet-border bg-fleet-page/40 p-4">
                            <p class="text-xs text-fleet-muted">
                                {{ $log->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                — {{ $log->user?->name ?? __('Usuário removido') }}
                            </p>
                            @php
                                $pairs = $log->diffSnapshots();
                            @endphp
                            @if (count($pairs) === 0)
                                <p class="mt-2 text-sm text-fleet-secondary">{{ __('Nenhum campo alterado (registro de salvamento).') }}</p>
                            @else
                                <table class="mt-3 w-full text-left text-sm">
                                    <thead>
                                        <tr class="text-xs uppercase text-fleet-muted">
                                            <th class="pb-2 pr-2 font-medium">{{ __('Campo') }}</th>
                                            <th class="pb-2 pr-2 font-medium">{{ __('Antes') }}</th>
                                            <th class="pb-2 font-medium">{{ __('Depois') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-fleet-border">
                                        @foreach ($pairs as $field => $pair)
                                            <tr>
                                                <td class="py-2 pr-2 align-top text-fleet-ink">
                                                    {{ \App\Models\TripChangeLog::labelForField($field) }}
                                                </td>
                                                <td class="py-2 pr-2 align-top text-fleet-secondary">
                                                    {{ $this->formatSnapshotValue($field, $pair['before'], $vehiclePlates, $driverNames) }}
                                                </td>
                                                <td class="py-2 align-top text-fleet-ink">
                                                    {{ $this->formatSnapshotValue($field, $pair['after'], $vehiclePlates, $driverNames) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-fleet-secondary">{{ __('Ainda não há alterações registradas para esta viagem.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
