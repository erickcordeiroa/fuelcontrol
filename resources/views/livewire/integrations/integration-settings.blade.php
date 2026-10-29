@php
    $usagePercent = min(100, (int) round($currentMonth['billable'] / max(1, $freePerMonth) * 100));
    [$statusColor, $statusLabel] = match (true) {
        $credential === null => ['#94a3b8', __('Não configurada')],
        $credential->isVerified() => ['#10b981', __('Funcionando')],
        $credential->last_error !== null => ['#dc2626', __('Com erro')],
        default => ['#F97316', __('Aguardando teste')],
    };
@endphp

<div class="w-full space-y-8">
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-xl border border-fleet-danger/30 bg-fleet-danger/10 px-4 py-3 text-sm text-fleet-danger">
            {{ session('error') }}
        </div>
    @endif

    <div>
        <h1 class="fleet-page-title">{{ __('Integrações') }}</h1>
        <p class="fleet-page-lead">{{ __('Serviços externos usados pela sua empresa. Cada empresa usa a própria conta e paga diretamente ao fornecedor.') }}</p>
    </div>

    {{-- Google --}}
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="fleet-panel space-y-4 xl:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="fleet-section-title">{{ __('Google Maps · Geocoding API') }}</p>
                    <p class="mt-1 text-fleet-body text-fleet-secondary">
                        {{ __('Encontra a coordenada dos endereços de clientes novos. É opcional: sem ela, informe latitude/longitude na planilha ou arraste o pino no mapa. O mapa e a otimização das rotas não usam o Google.') }}
                    </p>
                </div>
                <x-fleet.status-pill :color="$statusColor" :label="$statusLabel" />
            </div>

            @if ($credential)
                <dl class="grid gap-3 rounded-xl border border-fleet-border bg-fleet-page px-4 py-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-fleet-muted">{{ __('Chave cadastrada') }}</dt>
                        <dd class="font-mono font-semibold text-fleet-ink">{{ $credential->maskedKey() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-fleet-muted">{{ __('Último teste') }}</dt>
                        <dd class="font-semibold text-fleet-ink">{{ $credential->last_checked_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="flex items-end gap-2 sm:justify-end">
                        <button type="button" wire:click="testKey" class="fleet-btn--muted fleet-btn--sm" wire:loading.attr="disabled" wire:target="testKey">{{ __('Testar novamente') }}</button>
                        <button type="button" wire:click="removeKey" wire:confirm="{{ __('Remover a chave? A busca automática de endereços será desligada.') }}" class="fleet-btn--outline fleet-btn--sm">{{ __('Remover') }}</button>
                    </div>
                    @if ($credential->last_error)
                        <div class="sm:col-span-3">
                            <dt class="text-xs text-fleet-muted">{{ __('Erro do último teste') }}</dt>
                            <dd class="text-fleet-danger">{{ $credential->last_error }}</dd>
                        </div>
                    @endif
                </dl>
            @endif

            <form wire:submit="save" class="space-y-2">
                <label class="fleet-label" for="google-api-key">{{ $credential ? __('Trocar chave') : __('Chave da API') }}</label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input id="google-api-key" type="password" wire:model="apiKey" autocomplete="off" spellcheck="false" class="fleet-field font-mono" placeholder="AIza…" />
                    <button type="submit" class="fleet-btn--primary shrink-0" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ __('Salvar e testar') }}</span>
                        <span wire:loading wire:target="save">{{ __('Testando…') }}</span>
                    </button>
                </div>
                @error('apiKey') <p class="text-xs text-fleet-danger">{{ $message }}</p> @enderror
                <p class="text-xs text-fleet-muted">{{ __('A chave é guardada criptografada e não volta a ser exibida. O teste faz 1 consulta no Google.') }}</p>
            </form>
        </div>

        <div class="fleet-panel space-y-3">
            <p class="fleet-section-title">{{ __('Como criar a chave') }}</p>
            <ol class="list-decimal space-y-2 pl-5 text-sm text-fleet-secondary">
                <li>{{ __('Crie um projeto no Google Cloud Console e ative o faturamento (o Google pede cartão mesmo dentro da faixa grátis).') }}</li>
                <li>{{ __('Em "APIs e serviços", ative a Geocoding API.') }}</li>
                <li>{{ __('Em "Credenciais", crie uma chave de API.') }}</li>
                <li>
                    {{ __('Restrinja a chave: só a Geocoding API e só o IP do nosso servidor') }}
                    @if ($serverIp)
                        (<span class="font-mono font-semibold text-fleet-ink">{{ $serverIp }}</span>).
                    @else
                        {{ __('(peça o IP ao suporte).') }}
                    @endif
                </li>
                <li>{{ __('Recomendado: em "Cotas", limite a Geocoding API a :daily consultas por dia para não sair da faixa grátis.', ['daily' => number_format(intdiv($freePerMonth, 30), 0, ',', '.')]) }}</li>
            </ol>
        </div>
    </div>

    {{-- Consumo e custos --}}
    <div class="fleet-panel">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <p class="fleet-section-title">{{ __('Consumo do Google em :month', ['month' => now()->translatedFormat('F/Y')]) }}</p>
                <p class="mt-1 text-fleet-body text-fleet-secondary">{{ __('Estimativa pela tabela pública do Google, em dólar. A fatura oficial fica no Google Cloud Console.') }}</p>
            </div>
            <dl class="grid shrink-0 grid-cols-3 gap-6 text-right">
                <div>
                    <dt class="text-xs text-fleet-muted">{{ __('Cobráveis') }}</dt>
                    <dd class="text-xl font-bold tabular-nums text-fleet-ink">{{ number_format($currentMonth['billable'], 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-fleet-muted">{{ __('Gasto até agora') }}</dt>
                    <dd class="text-xl font-bold tabular-nums text-fleet-ink">US$ {{ number_format($currentMonth['cost'], 2, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-fleet-muted">{{ __('Projeção do mês') }}</dt>
                    <dd class="text-xl font-bold tabular-nums {{ $currentMonth['projectedCost'] > 0 ? 'text-fleet-tertiary' : 'text-fleet-ink' }}">US$ {{ number_format($currentMonth['projectedCost'], 2, ',', '.') }}</dd>
                </div>
            </dl>
        </div>
        <div class="mt-4">
            <div class="h-2 overflow-hidden rounded-full bg-fleet-page" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $usagePercent }}" aria-label="{{ __('Uso da faixa grátis') }}">
                <div class="h-full rounded-full {{ $usagePercent >= 80 ? 'bg-fleet-tertiary' : 'bg-fleet-primary' }}" style="width: {{ $usagePercent }}%"></div>
            </div>
            <p class="mt-2 text-xs text-fleet-muted">
                {{ __(':used de :free consultas grátis do mês (:percent%) · projeção de :projected consultas', [
                    'used' => number_format($currentMonth['billable'], 0, ',', '.'),
                    'free' => number_format($freePerMonth, 0, ',', '.'),
                    'percent' => $usagePercent,
                    'projected' => number_format($currentMonth['projected'], 0, ',', '.'),
                ]) }}
                @if ($currentMonth['notBilled'] > 0)
                    · {{ trans_choice(':count consulta com erro (não cobrada)|:count consultas com erro (não cobradas)', $currentMonth['notBilled']) }}
                @endif
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
                    <th class="px-4 py-3 text-right">{{ __('Cobráveis') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Com erro (não cobradas)') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Gasto estimado') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-fleet-border">
                @foreach ($history as $month)
                    <tr wire:key="usage-{{ $month['month']->format('Y-m') }}">
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-fleet-ink">{{ ucfirst($month['month']->translatedFormat('F/Y')) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($month['billable'], 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-fleet-secondary">{{ number_format($month['notBilled'], 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums {{ $month['cost'] > 0 ? 'text-fleet-tertiary' : 'text-fleet-ink' }}">
                            {{ $month['cost'] > 0 ? 'US$ '.number_format($month['cost'], 2, ',', '.') : __('Grátis') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
