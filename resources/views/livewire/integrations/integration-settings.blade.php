@php
    $usagePercent = min(100, (int) round($currentMonth['billable'] / max(1, $quota) * 100));
    $remaining = max(0, $quota - $currentMonth['billable']);
@endphp

<div class="w-full space-y-8">
    <div>
        <h1 class="fleet-page-title">{{ __('Integrações') }}</h1>
        <p class="fleet-page-lead">{{ __('Serviços usados pelo Fuelly em nome da sua empresa. Já estão incluídos no seu plano: não é preciso criar conta nem cadastrar chave.') }}</p>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="fleet-panel space-y-4 xl:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="fleet-section-title">{{ __('Busca de endereços') }}</p>
                    <p class="mt-1 text-fleet-body text-fleet-secondary">
                        {{ __('Encontra a localização dos clientes novos quando os pedidos são importados. Endereços que o sistema já conhece são reaproveitados e não contam na cota.') }}
                    </p>
                </div>
                @if ($serviceAvailable)
                    <x-fleet.status-pill color="#175641" :label="__('Funcionando')" />
                @else
                    <x-fleet.status-pill color="#B42318" :label="__('Indisponível')" />
                @endif
            </div>

            <div>
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-medium text-fleet-ink">{{ __('Uso em :month', ['month' => now()->translatedFormat('F/Y')]) }}</p>
                    <p class="font-display text-2xl font-medium tabular-nums text-fleet-ink">
                        {{ number_format($currentMonth['billable'], 0, ',', '.') }}
                        <span class="font-sans text-sm font-normal text-fleet-secondary">{{ __('de :quota', ['quota' => number_format($quota, 0, ',', '.')]) }}</span>
                    </p>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-fleet-surface" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $usagePercent }}" aria-label="{{ __('Uso da cota de busca de endereços') }}">
                    <div class="h-full rounded-full {{ $usagePercent >= 80 ? 'bg-fleet-tertiary' : 'bg-fleet-primary' }}" style="width: {{ $usagePercent }}%"></div>
                </div>
                <p class="mt-2 text-sm text-fleet-secondary">
                    {{ trans_choice(':count busca restante neste mês.|:count buscas restantes neste mês.', $remaining) }}
                    @if ($currentMonth['notBilled'] > 0)
                        {{ trans_choice(':count consulta com erro não foi contada.|:count consultas com erro não foram contadas.', $currentMonth['notBilled']) }}
                    @endif
                </p>
            </div>
        </div>

        <div class="fleet-panel space-y-3">
            <p class="fleet-section-title">{{ __('Quando a cota acaba') }}</p>
            <ul class="space-y-2 text-sm text-fleet-secondary">
                <li>{{ __('Os clientes novos ficam "Aguardando" até o próximo mês.') }}</li>
                <li>{{ __('Você pode arrastar o pino até o local na tela de entregas ou informar latitude e longitude na planilha.') }}</li>
                <li>{{ __('Depois da primeira entrega, a posição do motorista vale para sempre.') }}</li>
            </ul>
            <p class="border-t border-fleet-border pt-3 text-sm text-fleet-secondary">
                {{ trans_choice(':count cliente aproveitou um endereço já conhecido.|:count clientes aproveitaram endereços já conhecidos.', $reusedAddresses) }}
            </p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-fleet-border bg-fleet-card shadow-fleet">
        <div class="border-b border-fleet-border px-4 py-3">
            <p class="fleet-section-title">{{ __('Meses anteriores') }}</p>
        </div>
        <table class="min-w-full divide-y divide-fleet-border text-fleet-body">
            <thead class="fleet-table-head">
                <tr>
                    <th class="px-4 py-3">{{ __('Mês') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Buscas na cota') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Com erro (não contadas)') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-fleet-border">
                @foreach ($history as $month)
                    <tr wire:key="usage-{{ $month['month']->format('Y-m') }}">
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-fleet-ink">{{ ucfirst($month['month']->translatedFormat('F/Y')) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($month['billable'], 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-fleet-secondary">{{ number_format($month['notBilled'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
