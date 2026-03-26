<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-fleet-ink">{{ __('Painel operacional') }}</h1>
        <p class="mt-1 text-sm text-fleet-secondary">{{ __('Indicadores de combustível e despesas do período (mês).') }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-fleet-dark p-6 text-white shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ __('Custo combustível') }}</p>
            <p class="mt-3 text-3xl font-bold">R$ {{ number_format($metrics['total_fuel_cost'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Outras despesas') }}</p>
            <p class="mt-3 text-2xl font-bold text-fleet-ink">R$ {{ number_format($metrics['total_other_expenses'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Total operacional') }}</p>
            <p class="mt-3 text-2xl font-bold text-fleet-ink">R$ {{ number_format($metrics['total_operational_cost'], 2, ',', '.') }}</p>
            <p class="mt-2 text-xs text-fleet-muted">{{ __('Combustível + pedágio, ajudante e alimentação') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Litros abastecidos') }}</p>
            <p class="mt-3 text-2xl font-bold text-fleet-ink">{{ number_format($metrics['total_liters'], 2, ',', '.') }} L</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm lg:col-span-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Consumo médio (por diário)') }}</p>
            <p class="mt-2 text-xl font-semibold text-fleet-ink">
                @if ($metrics['efficiency_km_per_liter'] !== null)
                    {{ $metrics['efficiency_km_per_liter'] }} km/L
                @else
                    —
                @endif
            </p>
            <p class="mt-1 text-xs text-fleet-muted">{{ __('Média do km/L por registro no diário') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm lg:col-span-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('Preço médio por KM (por diário)') }}</p>
            <p class="mt-2 text-xl font-semibold text-fleet-ink">
                @if ($metrics['cost_per_km'] !== null)
                    R$ {{ number_format($metrics['cost_per_km'], 2, ',', '.') }}
                @else
                    —
                @endif
            </p>
            <p class="mt-1 text-xs text-fleet-muted">{{ __('Média do R$/km de combustível por registro') }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-6 shadow-sm lg:col-span-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-fleet-muted">{{ __('KM totais') }}</p>
            <p class="mt-2 text-xl font-semibold text-fleet-ink">{{ number_format($metrics['total_km'], 0, ',', '.') }} km</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <a
            href="{{ route('logbook') }}"
            wire:navigate
            class="inline-flex items-center rounded-xl bg-fleet-dark px-5 py-3 text-sm font-semibold text-white hover:opacity-90"
        >
            {{ __('Abrir diário de bordo') }}
        </a>
        <a
            href="{{ route('reports') }}"
            wire:navigate
            class="inline-flex items-center rounded-xl border border-fleet-border bg-fleet-card px-5 py-3 text-sm font-semibold text-fleet-ink hover:bg-fleet-page"
        >
            {{ __('Ver relatórios') }}
        </a>
    </div>
</div>
