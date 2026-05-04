@props(['alerts'])

@if ($alerts->isNotEmpty())
    <div class="rounded-2xl border border-fleet-danger/40 bg-fleet-danger/10 p-4 shadow-fleet">
        <div class="flex items-start gap-3">
            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-fleet-danger/15 text-fleet-danger" aria-hidden="true">
                <x-icons.exclamation-triangle class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1 space-y-1">
                <p class="text-sm font-semibold text-fleet-danger">
                    {{ __('Trocas de óleo pendentes') }}
                </p>
                <ul class="space-y-1 text-sm text-fleet-danger">
                    @foreach ($alerts as $alert)
                        <li>
                            <span class="font-semibold">{{ $alert['plate'] }}</span>
                            — {{ __('deve trocar o óleo') }}
                            <span class="text-xs text-fleet-danger/80">
                                ·
                                @if ($alert['km_remaining'] <= 0)
                                    {{ __('vencido em :n km', ['n' => number_format(abs($alert['km_remaining']), 0, ',', '.')]) }}
                                @else
                                    {{ __(':n km restantes', ['n' => number_format($alert['km_remaining'], 0, ',', '.')]) }}
                                @endif
                                ·
                                @if ($alert['days_remaining'] <= 0)
                                    {{ __('6 meses vencidos há :d dias', ['d' => abs($alert['days_remaining'])]) }}
                                @else
                                    {{ __(':d dias para 6 meses', ['d' => $alert['days_remaining']]) }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
