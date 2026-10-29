@php
    use App\Enums\DeliveryRouteStatus;
@endphp

<div class="w-full space-y-6" wire:poll.15s.visible>
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="fleet-page-title">{{ __('Acompanhamento de rotas') }}</h1>
            <p class="fleet-page-lead">
                {{ __('Posição dos entregadores e situação de cada entrega.') }}
                <span class="inline-flex items-center gap-1.5 text-fleet-success">
                    <span class="h-2 w-2 rounded-full bg-fleet-success"></span>{{ __('Atualiza a cada 15 s') }}
                </span>
            </p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="fleet-label" for="monitor-date">{{ __('Data') }}</label>
                <input id="monitor-date" type="date" wire:model.live="date" class="fleet-field" />
            </div>
            <a href="{{ route('deliveries.planner', ['data' => $date]) }}" wire:navigate class="fleet-btn--outline">
                <x-icons.map-pin class="h-5 w-5 shrink-0" />
                {{ __('Planejar entregas') }}
            </a>
        </div>
    </div>

    @if ($routes->isEmpty())
        <div class="fleet-panel text-center">
            <p class="font-semibold text-fleet-ink">{{ __('Nenhuma rota nesta data.') }}</p>
            <p class="mt-1 text-fleet-body text-fleet-secondary">{{ __('Importe os pedidos e crie as rotas na tela de entregas.') }}</p>
        </div>
    @else
        @if ($route)
            {{-- Indicadores da rota selecionada --}}
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="fleet-panel--inverted">
                    <p class="text-sm font-medium text-fleet-navy/80">{{ __('Entregas concluídas') }}</p>
                    <p class="mt-3 font-display text-3xl font-medium tabular-nums">{{ $summary['delivered'] }} <span class="text-lg font-semibold text-fleet-navy/80">{{ __('de :total', ['total' => $summary['total']]) }}</span></p>
                    @if ($summary['failed'] > 0)
                        <p class="mt-1 text-xs text-fleet-navy">{{ trans_choice(':count não entregue|:count não entregues', $summary['failed']) }}</p>
                    @endif
                </div>
                <div class="fleet-panel">
                    <p class="fleet-section-title">{{ __('Valor entregue') }}</p>
                    <p class="mt-3 font-display text-2xl font-medium tabular-nums text-fleet-navy">{{ format_money_brl($summary['amountDelivered']) }}</p>
                    <p class="mt-2 text-xs text-fleet-muted">{{ __('de :total na rota', ['total' => format_money_brl($summary['amountTotal'])]) }}</p>
                </div>
                <div class="fleet-panel">
                    <p class="fleet-section-title">{{ __('Próxima parada') }}</p>
                    @if ($summary['nextStop'])
                        <p class="mt-3 truncate text-lg font-bold text-fleet-ink">{{ $summary['nextStop']->sequence }}. {{ $summary['nextStop']->delivery->customer->name }}</p>
                        <p class="mt-1 text-xs text-fleet-muted">
                            @if ($summary['etaMinutes'] !== null)
                                {{ __('Chegada em ~:min min (:time)', ['min' => $summary['etaMinutes'], 'time' => now()->addMinutes($summary['etaMinutes'])->format('H:i')]) }}
                            @else
                                {{ __('Sem posição do motorista') }}
                            @endif
                        </p>
                    @else
                        <p class="mt-3 text-lg font-bold text-fleet-ink">{{ __('Todas visitadas') }}</p>
                    @endif
                </div>
                <div class="fleet-panel">
                    <p class="fleet-section-title">{{ __('Km restantes') }}</p>
                    <p class="mt-3 font-display text-2xl font-medium tabular-nums text-fleet-navy">{{ number_format($summary['kmRemaining'], 1, ',', '.') }} km</p>
                    <p class="mt-2 text-xs {{ $summary['isIdle'] ? 'font-semibold text-fleet-tertiary' : 'text-fleet-muted' }}">
                        @if ($summary['lastPingMinutes'] === null)
                            {{ __('Motorista ainda não enviou posição') }}
                        @else
                            {{ __('Última posição há :min min', ['min' => $summary['lastPingMinutes']]) }}
                        @endif
                    </p>
                </div>
            </div>

            {{-- Mapa em largura total --}}
            <div>
                <x-fleet.route-map :payload="$mapPayload" height="h-[480px] lg:h-[560px]" />
                <div class="mt-2 flex flex-wrap gap-4 text-xs text-fleet-secondary">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-fleet-success"></span>{{ __('Entregue') }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-fleet-tertiary"></span>{{ __('Em rota') }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-fleet-primary"></span>{{ __('Roteirizada') }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-fleet-danger"></span>{{ __('Não entregue') }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-full border-2 border-fleet-ink bg-fleet-warning"></span>{{ __('Motorista') }}</span>
                </div>
            </div>
        @endif

        {{-- Motoristas e entregas lado a lado --}}
        <div class="grid gap-4 {{ $route ? 'lg:grid-cols-2' : '' }}">
            <div class="flex max-h-[560px] flex-col overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-fleet">
                <div class="flex items-center justify-between gap-2 border-b border-fleet-border px-4 py-3">
                    <p class="fleet-section-title">{{ __('Motoristas') }}</p>
                    <p class="text-xs text-fleet-secondary">{{ trans_choice(':count rota|:count rotas', $routes->count()) }}</p>
                </div>
                <div class="grid gap-3 overflow-y-auto p-3 sm:grid-cols-2 lg:grid-cols-1 2xl:grid-cols-2">
                @foreach ($routes as $item)
                    @php
                        $finished = $item->delivered_count + $item->failed_count;
                        $progress = $item->stops_count > 0 ? round($finished / $item->stops_count * 100) : 0;
                        $idle = in_array($item->id, $idleRouteIds, true);
                        $isSelected = $route?->id === $item->id;
                    @endphp
                    <button
                        type="button"
                        wire:key="monitor-route-{{ $item->id }}"
                        wire:click="selectRoute({{ $item->id }})"
                        class="rounded-2xl border bg-fleet-card p-4 text-left shadow-fleet transition hover:border-fleet-primary/50
                            {{ $isSelected ? 'border-fleet-primary ring-1 ring-fleet-primary' : ($idle ? 'border-fleet-tertiary' : 'border-fleet-border') }}"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="truncate font-semibold text-fleet-ink">{{ $item->driver?->name ?? __('Sem motorista') }}</p>
                            <span class="shrink-0 text-xs font-semibold tabular-nums text-fleet-secondary">{{ $finished }}/{{ $item->stops_count }}</span>
                        </div>
                        <p class="truncate text-xs text-fleet-secondary">{{ $item->name }} · {{ $item->vehicle?->plate ?? __('sem veículo') }}</p>
                        <div class="mt-3 flex h-1.5 overflow-hidden rounded-full bg-fleet-page">
                            <span class="block h-full {{ $idle ? 'bg-fleet-tertiary' : 'bg-fleet-primary' }}" style="width: {{ $progress }}%"></span>
                        </div>
                        <p class="mt-2 text-xs font-medium {{ $idle ? 'text-fleet-tertiary' : 'text-fleet-secondary' }}">
                            @if ($idle)
                                {{ __('Parado há :min min', ['min' => (int) $item->latestLocation->recorded_at->diffInMinutes(now())]) }}
                            @elseif ($item->status === DeliveryRouteStatus::InProgress)
                                {{ __('Em movimento') }}
                            @else
                                {{ $item->status->label() }}
                            @endif
                        </p>
                    </button>
                @endforeach
                </div>
            </div>

            @if ($route)
                <div class="flex max-h-[560px] flex-col overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-fleet">
                    <div class="flex items-center justify-between gap-2 border-b border-fleet-border px-4 py-3">
                        <p class="fleet-section-title">{{ __('Entregas') }} · {{ $route->name }}</p>
                        <p class="text-xs text-fleet-secondary">{{ __(':km km estimados', ['km' => number_format(($route->total_distance_m ?? 0) / 1000, 1, ',', '.')]) }}</p>
                    </div>
                    <ol class="divide-y divide-fleet-border overflow-y-auto">
                        @foreach ($route->stops as $stop)
                            @php
                                $delivery = $stop->delivery;
                                $coordinates = $delivery->customer->coordinates();
                            @endphp
                            <li wire:key="stop-{{ $stop->id }}" class="grid grid-cols-[28px_minmax(0,1fr)_auto] items-start gap-3 px-4 py-3">
                                <span class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold text-white" style="background-color: {{ $delivery->status->color() }}">{{ $stop->sequence }}</span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-fleet-ink">{{ $delivery->customer->name }}</p>
                                    <p class="truncate text-xs text-fleet-secondary">{{ $delivery->customer->fullAddress() }}</p>
                                    @if ($delivery->customer->reference)
                                        <p class="truncate text-xs text-fleet-muted">{{ __('Ref.: :ref', ['ref' => $delivery->customer->reference]) }}</p>
                                    @endif
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <x-fleet.status-pill :color="$delivery->status->color()" :label="$delivery->status->label()" />
                                        @if ($delivery->delivered_at)
                                            <span class="text-xs text-fleet-secondary">{{ $delivery->delivered_at->format('H:i') }}</span>
                                        @endif
                                        @if ($delivery->failure_reason)
                                            <span class="text-xs text-fleet-danger">{{ $delivery->failure_reason }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-medium tabular-nums text-fleet-ink">{{ format_money_brl($delivery->amount) }}</p>
                                    @if ($coordinates)
                                        <a
                                            href="https://www.google.com/maps/dir/?api=1&destination={{ $coordinates['lat'] }},{{ $coordinates['lng'] }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="mt-1 inline-block text-xs font-semibold text-fleet-primary hover:underline"
                                        >{{ __('Navegar') }}</a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>
    @endif
</div>
