@php
    $summary = $report['summary'];
    $formatRate = fn (?float $rate): string => $rate === null ? '—' : number_format($rate, 1, ',', '.').'%';
@endphp

<div class="space-y-8">
    <x-fleet.report-tabs />

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="fleet-page-title">{{ __('Entregas e custo por entrega') }}</h1>
            <p class="fleet-page-lead">{{ __('Entregas de cada motorista nas rotas do período, com o gasto lançado no Diário de Bordo (combustível e despesas) no mesmo dia.') }}</p>
        </div>
        @if ($canExport)
            <a href="{{ $exportCsvUrl }}" class="fleet-btn--outline fleet-btn--sm self-start lg:self-auto">{{ __('Exportar Excel') }}</a>
        @else
            <p class="max-w-xs text-sm text-fleet-secondary">
                {{ __('Exportar em Excel está nos planos a partir de 10 veículos.') }}
                <a href="{{ route('subscription.index') }}" wire:navigate class="font-medium text-fleet-primary-strong underline-offset-4 hover:underline">{{ __('Ver planos') }}</a>
            </p>
        @endif
    </div>

    <div class="rounded-2xl border border-fleet-border bg-fleet-card p-4 shadow-fleet">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="fleet-label" for="delivery-report-start">{{ __('De') }}</label>
                <x-fleet.br-date-input id="delivery-report-start" for="startDate" :live="true" />
                @error('startDate') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="fleet-label" for="delivery-report-end">{{ __('Até') }}</label>
                <x-fleet.br-date-input id="delivery-report-end" for="endDate" :live="true" />
                @error('endDate') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="fleet-label" for="delivery-report-driver">{{ __('Motorista') }}</label>
                <select id="delivery-report-driver" wire:model.live="filterDriverId" class="fleet-field">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach ($drivers as $driver)
                        <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="applyFilters" class="fleet-btn--primary fleet-btn--block">{{ __('Filtrar') }}</button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="fleet-panel--inverted !p-5">
            <p class="text-sm font-medium text-fleet-navy/80">{{ __('Entregues') }}</p>
            <p class="mt-2 font-display text-2xl font-medium tabular-nums">{{ number_format($summary['delivered'], 0, ',', '.') }} <span class="text-base font-semibold text-fleet-navy/80">{{ __('de :total', ['total' => number_format($summary['stops'], 0, ',', '.')]) }}</span></p>
            <p class="mt-1 text-xs text-fleet-navy">{{ __('Taxa de entrega: :rate', ['rate' => $formatRate($summary['successRate'])]) }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-fleet">
            <p class="text-sm font-medium text-fleet-muted">{{ __('Não entregues') }}</p>
            <p class="mt-2 font-display text-2xl font-medium tabular-nums {{ $summary['failed'] > 0 ? 'text-fleet-danger' : 'text-fleet-navy' }}">{{ number_format($summary['failed'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-fleet-muted">{{ trans_choice('{0} Todas as paradas tiveram baixa|{1} :count parada sem baixa|[2,*] :count paradas sem baixa', $summary['open']) }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-fleet">
            <p class="text-sm font-medium text-fleet-muted">{{ __('Valor entregue') }}</p>
            <p class="mt-2 whitespace-nowrap font-display text-lg font-medium tabular-nums text-fleet-navy sm:text-2xl">{{ format_money_brl($summary['amountDelivered']) }}</p>
            <p class="mt-1 text-xs text-fleet-muted">{{ __('de :total nas rotas', ['total' => format_money_brl($summary['amountTotal'])]) }}</p>
        </div>
        <div class="rounded-2xl border border-fleet-border bg-fleet-card p-5 shadow-fleet">
            <p class="text-sm font-medium text-fleet-muted">{{ __('Custo por entrega') }}</p>
            <p class="mt-2 whitespace-nowrap font-display text-lg font-medium tabular-nums text-fleet-navy sm:text-2xl">{{ $summary['costPerDelivery'] !== null ? format_money_brl($summary['costPerDelivery']) : '—' }}</p>
            <p class="mt-1 text-xs text-fleet-muted">
                @if ($summary['cost'] > 0)
                    {{ __('Gasto de :cost', ['cost' => format_money_brl($summary['cost'])]) }}@if ($summary['costShare'] !== null){{ __(' · :share% do valor entregue', ['share' => number_format($summary['costShare'], 1, ',', '.')]) }}@endif
                @else
                    {{ __('Sem viagens lançadas no Diário de Bordo no período') }}
                @endif
            </p>
        </div>
    </div>

    <p class="-mt-4 text-xs text-fleet-secondary">
        {{ trans_choice(':count rota|:count rotas', $summary['routes']) }} ·
        {{ __(':km km planejados', ['km' => number_format($summary['plannedKm'], 1, ',', '.')]) }} ·
        {{ __(':km km rodados no Diário de Bordo', ['km' => number_format($summary['realKm'], 0, ',', '.')]) }}
        @if ($report['unrouted'] > 0)
            · {{ trans_choice(':count pedido ficou sem rota|:count pedidos ficaram sem rota', $report['unrouted']) }}
        @endif
    </p>

    @if ($summary['routes'] === 0)
        <div class="fleet-panel text-center">
            <p class="font-semibold text-fleet-ink">{{ __('Nenhuma rota no período.') }}</p>
            <p class="mt-1 text-fleet-body text-fleet-secondary">{{ __('As entregas aparecem aqui depois que as rotas são criadas na tela de entregas.') }}</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-fleet">
            <div class="border-b border-fleet-border px-4 py-3">
                <h3 class="text-sm font-semibold text-fleet-ink">{{ __('Por motorista') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="whitespace-nowrap bg-fleet-page text-left text-xs font-semibold text-fleet-secondary">
                        <tr>
                            <th class="px-4 py-2.5">{{ __('Motorista') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Entregues') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Não entregues') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Taxa') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Valor entregue') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Custo por entrega') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Km planejados') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Km rodados') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Tempo de rota') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-fleet-border">
                        @foreach ($report['drivers'] as $row)
                            <tr wire:key="delivery-report-driver-{{ $loop->index }}">
                                <td class="whitespace-nowrap px-4 py-3 font-semibold text-fleet-ink">{{ $row['driver'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $row['delivered'] }} <span class="text-fleet-muted">/ {{ $row['stops'] }}</span></td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $row['failed'] > 0 ? 'text-fleet-danger' : '' }}">{{ $row['failed'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $formatRate($row['successRate']) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ format_money_brl($row['amountDelivered']) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                                    <span class="font-semibold text-fleet-ink">{{ $row['costPerDelivery'] !== null ? format_money_brl($row['costPerDelivery']) : '—' }}</span>
                                    @if ($row['cost'] > 0)
                                        <span class="block text-xs text-fleet-muted">{{ __('gasto :cost', ['cost' => format_money_brl($row['cost'])]) }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ number_format($row['plannedKm'], 1, ',', '.') }} km</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $row['realKm'] > 0 ? number_format($row['realKm'], 0, ',', '.').' km' : '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $row['avgRouteMinutes'] !== null ? intdiv($row['avgRouteMinutes'], 60).'h'.str_pad((string) ($row['avgRouteMinutes'] % 60), 2, '0', STR_PAD_LEFT) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-5">
            <div class="rounded-2xl border border-fleet-border bg-fleet-card p-4 shadow-fleet lg:col-span-2">
                <h3 class="text-sm font-semibold text-fleet-ink">{{ __('Motivos de não entrega') }}</h3>
                @if ($report['reasons'] === [])
                    <p class="mt-3 text-sm text-fleet-secondary">{{ __('Nenhuma entrega deixou de ser feita no período.') }}</p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($report['reasons'] as $reason)
                            <li wire:key="delivery-report-reason-{{ $loop->index }}">
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="text-fleet-ink">{{ $reason['label'] }}</span>
                                    <span class="shrink-0 tabular-nums text-fleet-secondary">{{ $reason['count'] }} · {{ number_format($reason['percent'], 0, ',', '.') }}%</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-fleet-page">
                                    <span class="block h-full bg-fleet-danger" style="width: {{ $reason['percent'] }}%"></span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-fleet lg:col-span-3">
                <div class="border-b border-fleet-border px-4 py-3">
                    <h3 class="text-sm font-semibold text-fleet-ink">{{ __('Por dia') }}</h3>
                </div>
                <div class="max-h-96 overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 whitespace-nowrap bg-fleet-page text-left text-xs font-semibold text-fleet-secondary">
                            <tr>
                                <th class="px-4 py-2.5">{{ __('Data') }}</th>
                                <th class="px-4 py-2.5 text-right">{{ __('Entregues') }}</th>
                                <th class="px-4 py-2.5 text-right">{{ __('Não entregues') }}</th>
                                <th class="px-4 py-2.5 text-right">{{ __('Valor entregue') }}</th>
                                <th class="px-4 py-2.5 text-right">{{ __('Custo por entrega') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-fleet-border">
                            @foreach (array_reverse($report['days']) as $day)
                                <tr wire:key="delivery-report-day-{{ $day['date'] }}">
                                    <td class="whitespace-nowrap px-4 py-2.5">
                                        <a href="{{ route('deliveries.monitor', ['data' => $day['date']]) }}" wire:navigate class="font-medium text-fleet-primary-strong underline-offset-4 hover:underline">{{ \Carbon\Carbon::parse($day['date'])->format('d/m/Y') }}</a>
                                    </td>
                                    <td class="px-4 py-2.5 text-right tabular-nums">{{ $day['delivered'] }} <span class="text-fleet-muted">/ {{ $day['stops'] }}</span></td>
                                    <td class="px-4 py-2.5 text-right tabular-nums {{ $day['failed'] > 0 ? 'text-fleet-danger' : '' }}">{{ $day['failed'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums">{{ format_money_brl($day['amountDelivered']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums">{{ $day['costPerDelivery'] !== null ? format_money_brl($day['costPerDelivery']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
