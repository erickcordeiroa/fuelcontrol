{{-- Abas dos relatórios: Combustível e gastos (todos) e Entregas (administrador). --}}
@if (auth()->user()->isAdmin())
    <nav class="flex gap-1 overflow-x-auto rounded-full border border-fleet-border bg-fleet-card p-1 shadow-fleet sm:inline-flex" aria-label="{{ __('Relatórios') }}">
        @foreach ([['reports', __('Combustível e gastos')], ['reports.deliveries', __('Entregas e custo por entrega')]] as [$routeName, $label])
            <a
                href="{{ route($routeName) }}"
                wire:navigate
                @class([
                    'shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition',
                    'bg-fleet-ink text-white' => request()->routeIs($routeName),
                    'text-fleet-secondary hover:bg-fleet-page hover:text-fleet-ink' => ! request()->routeIs($routeName),
                ])
                @if (request()->routeIs($routeName)) aria-current="page" @endif
            >{{ $label }}</a>
        @endforeach
    </nav>
@endif
