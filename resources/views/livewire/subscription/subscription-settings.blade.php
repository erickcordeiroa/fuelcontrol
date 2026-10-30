@php
    use App\Services\Billing\PlanLimits;
    $featureLabels = ['deliveries' => __('Entregas, rotas e acompanhamento'), 'report_export' => __('Exportar relatórios (PDF e Excel)')];
@endphp

<div class="w-full space-y-8">

    <div>
        <h1 class="fleet-page-title">{{ __('Assinatura') }}</h1>
        <p class="fleet-page-lead">{{ __('Seu plano, o uso de cada limite e a troca de plano. O pagamento online será liberado em breve; por enquanto a troca vale na hora.') }}</p>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="fleet-panel--inverted">
            <p class="text-sm font-medium text-fleet-navy/80">{{ __('Plano atual') }}</p>
            <p class="mt-2 font-display text-2xl font-medium">{{ $current['name'] }}</p>
            <p class="mt-1 font-display text-xl font-medium tabular-nums">
                {{ PlanLimits::formatPrice($current['price_cents']) }}@if ($current['price_cents'] !== null)<span class="font-sans text-sm font-normal text-fleet-secondary"> {{ __('/mês') }}</span>@endif
            </p>
            <p class="mt-3 text-sm text-fleet-secondary">
                @if ($subscription?->started_at)
                    {{ __('Desde :date', ['date' => $subscription->started_at->format('d/m/Y')]) }}
                @else
                    {{ __('Plano inicial da conta') }}
                @endif
            </p>
        </div>

        <div class="fleet-panel xl:col-span-2">
            <p class="fleet-section-title">{{ __('Uso do plano') }}</p>
            <dl class="mt-4 grid gap-5 sm:grid-cols-2">
                @foreach ($usage as $row)
                    <div wire:key="usage-{{ $row['limit'] }}">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-sm text-fleet-secondary">{{ ucfirst($row['label']) }}</dt>
                            <dd class="text-sm font-medium tabular-nums text-fleet-ink">
                                {{ number_format($row['used'], 0, ',', '.') }}
                                <span class="text-fleet-secondary">{{ $row['max'] === null ? __('· sem limite') : __('de :max', ['max' => number_format($row['max'], 0, ',', '.')]) }}</span>
                            </dd>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-fleet-surface" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $row['percent'] }}" aria-label="{{ ucfirst($row['label']) }}">
                            <div class="h-full rounded-full {{ $row['percent'] >= PlanLimits::WARNING_PERCENT ? 'bg-fleet-tertiary' : 'bg-fleet-primary' }}" style="width: {{ $row['max'] === null ? 0 : $row['percent'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    <div>
        <h2 class="fleet-section-title">{{ __('Planos') }}</h2>
        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            @foreach ($plans as $plan)
                <article wire:key="plan-{{ $plan['key'] }}" class="flex flex-col rounded-2xl border bg-fleet-card p-6 shadow-fleet {{ $plan['isCurrent'] ? 'border-fleet-primary ring-1 ring-fleet-primary' : 'border-fleet-border' }}">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-display text-xl font-medium text-fleet-ink">{{ $plan['name'] }}</h3>
                        @if ($plan['isCurrent'])
                            <span class="rounded-pill bg-fleet-info px-2.5 py-0.5 text-xs font-medium text-fleet-primary-strong">{{ __('Seu plano') }}</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-fleet-secondary">{{ $plan['description'] }}</p>
                    <p class="mt-4 font-display text-2xl font-medium tabular-nums text-fleet-ink">
                        {{ PlanLimits::formatPrice($plan['price_cents']) }}@if ($plan['price_cents'] !== null)<span class="font-sans text-sm font-normal text-fleet-secondary"> {{ __('/mês') }}</span>@endif
                    </p>
                    <ul class="mt-4 flex-1 space-y-2 text-sm text-fleet-ink">
                        @foreach (PlanLimits::LIMITS as $limit)
                            <li>
                                {{ $plan['limits'][$limit] === null ? __('Sem limite de :what', ['what' => PlanLimits::label($limit)]) : number_format($plan['limits'][$limit], 0, ',', '.').' '.PlanLimits::label($limit) }}
                            </li>
                        @endforeach
                        @foreach ($featureLabels as $feature => $label)
                            <li class="{{ $plan['features'][$feature] ? '' : 'text-fleet-muted line-through' }}">{{ $label }}</li>
                        @endforeach
                    </ul>

                    @if ($plan['overLimits'] !== [])
                        <p class="mt-4 rounded-xl bg-fleet-surface px-3 py-2 text-xs text-fleet-secondary">
                            {{ __('Você tem mais :what do que este plano permite. Nada será apagado, mas novos cadastros ficarão bloqueados.', ['what' => implode(' e ', $plan['overLimits'])]) }}
                        </p>
                    @endif

                    <div class="mt-6">
                        @if ($plan['isCurrent'])
                            <p class="text-sm font-medium text-fleet-secondary">{{ __('Plano em uso') }}</p>
                        @elseif ($plan['price_cents'] === null)
                            <p class="text-sm text-fleet-secondary">{{ __('Contratado com a nossa equipe. Fale com o suporte.') }}</p>
                        @else
                            <button
                                type="button"
                                wire:click="changePlan('{{ $plan['key'] }}')"
                                wire:confirm="{{ __('Mudar para o plano “:plan”?', ['plan' => $plan['name']]) }}"
                                class="{{ $plan['key'] === 'profissional' ? 'fleet-btn--primary' : 'fleet-btn--outline' }} fleet-btn--block"
                            >{{ __('Mudar para este plano') }}</button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-fleet-muted">{{ __('Valores de exemplo, sujeitos a alteração.') }}</p>
    </div>
</div>
