@php
    use App\Enums\DeliveryStatus;
    use App\Enums\GeocodeStatus;
@endphp

<div class="w-full space-y-8">
    @if (session('status'))
        <div class="rounded-xl border border-fleet-success/30 bg-fleet-success/10 px-4 py-3 text-sm text-fleet-profit">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="fleet-page-title">{{ __('Entregas') }}</h1>
            <p class="fleet-page-lead">{{ __('Importe os pedidos do dia, confira os endereços no mapa e crie as rotas.') }}</p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="fleet-label" for="planner-date">{{ __('Data das entregas') }}</label>
                <input id="planner-date" type="date" wire:model.live="date" class="fleet-field" />
            </div>
            <a href="{{ route('deliveries.monitor', ['data' => $date]) }}" wire:navigate class="fleet-btn--outline">
                <x-icons.map class="h-5 w-5 shrink-0" />
                {{ __('Acompanhar rotas') }}
            </a>
        </div>
    </div>

    @unless ($googleKeyConfigured)
        <div class="rounded-xl border border-fleet-tertiary/30 bg-fleet-tertiary/10 px-4 py-3 text-sm text-fleet-ink">
            <span class="font-semibold">{{ __('Busca automática de endereços desligada.') }}</span>
            {{ __('Cadastre a chave do Google para localizar clientes novos. Clientes já localizados continuam funcionando.') }}
            <a href="{{ route('integrations.index') }}" wire:navigate class="font-semibold text-fleet-primary underline-offset-2 hover:underline">{{ __('Ir para Integrações') }}</a>
        </div>
    @endunless

    {{-- Importação --}}
    <div class="fleet-panel">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="fleet-section-title">{{ __('Importar pedidos') }}</p>
                <p class="mt-1 text-fleet-body text-fleet-secondary">
                    {{ __('Planilha .xlsx ou .csv no formato do modelo. Os pedidos entram na data selecionada acima.') }}
                    <a href="{{ route('deliveries.template') }}" class="font-semibold text-fleet-primary underline-offset-2 hover:underline">{{ __('Baixar modelo') }}</a>
                </p>
            </div>
            <form wire:submit="import" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <input
                    id="planner-spreadsheet"
                    type="file"
                    wire:model="spreadsheet"
                    accept=".xlsx,.csv"
                    class="block w-full text-sm text-fleet-secondary file:mr-3 file:rounded-xl file:border-0 file:bg-fleet-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-fleet-primary hover:file:bg-fleet-primary/15"
                />
                <button type="submit" class="fleet-btn--primary shrink-0" wire:loading.attr="disabled" wire:target="spreadsheet,import">
                    <span wire:loading.remove wire:target="import">{{ __('Importar') }}</span>
                    <span wire:loading wire:target="import">{{ __('Importando…') }}</span>
                </button>
            </form>
        </div>
        @error('spreadsheet') <p class="mt-2 text-xs text-fleet-danger">{{ $message }}</p> @enderror

        @if ($lastImport)
            <div class="mt-4 rounded-xl border border-fleet-border bg-fleet-page px-4 py-3 text-sm">
                <p class="font-semibold text-fleet-ink">
                    {{ $lastImport->file_name }} · {{ $lastImport->status->label() }}
                </p>
                <p class="mt-0.5 text-fleet-secondary">
                    {{ __(':imported importado(s), :failed com erro, de :total linha(s).', ['imported' => $lastImport->imported_rows, 'failed' => $lastImport->failed_rows, 'total' => $lastImport->total_rows]) }}
                </p>
                @if (! empty($lastImport->errors))
                    <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto text-xs text-fleet-danger">
                        @foreach ($lastImport->errors as $error)
                            <li wire:key="import-error-{{ $loop->index }}">
                                @if ($error['row']) <span class="font-semibold">{{ __('Linha :row:', ['row' => $error['row']]) }}</span> @endif
                                {{ implode(' ', $error['messages']) }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>

    {{-- Indicadores --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="fleet-panel--inverted">
            <p class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ __('Pedidos do dia') }}</p>
            <p class="mt-3 text-3xl font-bold">{{ $stats['total'] }}</p>
        </div>
        <div class="fleet-panel">
            <p class="fleet-section-title">{{ __('Sem rota') }}</p>
            <p class="mt-3 text-2xl font-bold text-fleet-ink">{{ $stats['unrouted'] }}</p>
        </div>
        <div class="fleet-panel">
            <p class="fleet-section-title">{{ __('Revisar endereço') }}</p>
            <p class="mt-3 text-2xl font-bold {{ $stats['needsReview'] > 0 ? 'text-fleet-tertiary' : 'text-fleet-ink' }}">{{ $stats['needsReview'] }}</p>
            <p class="mt-2 text-xs text-fleet-muted">{{ __('Arraste o pino laranja até a porta do cliente') }}</p>
        </div>
        <div class="fleet-panel">
            <p class="fleet-section-title">{{ __('Aguardando localização') }}</p>
            <p class="mt-3 text-2xl font-bold text-fleet-ink">{{ $stats['waitingGeocode'] }}</p>
            <p class="mt-2 text-xs text-fleet-muted">{{ __('Processado pela fila (queue:work)') }}</p>
        </div>
    </div>

    {{-- Mapa + criação de rota --}}
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-fleet.route-map :payload="$mapPayload" />
            <div class="mt-2 flex flex-wrap gap-4 text-xs text-fleet-secondary">
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-fleet-muted"></span>{{ __('Sem rota') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-fleet-primary"></span>{{ __('Em rota') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-fleet-tertiary"></span>{{ __('Revisar posição') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-fleet-danger"></span>{{ __('Não localizado') }}</span>
            </div>
        </div>

        <div class="fleet-panel space-y-4">
            <div>
                <p class="fleet-section-title">{{ __('Criar rota') }}</p>
                <p class="mt-1 text-fleet-body text-fleet-secondary">{{ __('Escolha um setor ou marque os pedidos na tabela. A ordem das paradas é otimizada automaticamente.') }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach ($sectors as $sector)
                    <button
                        type="button"
                        wire:click="selectSector(@js($sector['sector']))"
                        wire:key="sector-{{ $loop->index }}"
                        class="fleet-btn--muted fleet-btn--sm"
                        @disabled($sector['routable'] === 0)
                    >
                        {{ $sector['sector'] !== '' ? $sector['sector'] : __('Sem setor') }}
                        <span class="text-fleet-muted">{{ $sector['routable'] }}/{{ $sector['total'] }}</span>
                    </button>
                @endforeach
            </div>

            <form wire:submit="createRoute" class="space-y-3">
                <p class="text-sm text-fleet-ink">
                    <span class="font-semibold">{{ count($selected) }}</span> {{ __('pedido(s) selecionado(s)') }}
                    @if (count($selected) > 0)
                        · <button type="button" wire:click="clearSelection" class="text-fleet-secondary underline-offset-2 hover:underline">{{ __('limpar') }}</button>
                    @endif
                </p>
                @error('selected') <p class="text-xs text-fleet-danger">{{ $message }}</p> @enderror

                <div>
                    <label class="fleet-label" for="route-name">{{ __('Nome da rota') }}</label>
                    <input id="route-name" type="text" wire:model="routeName" class="fleet-field" placeholder="{{ __('Setor Sul') }}" />
                    @error('routeName') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="fleet-label" for="route-driver">{{ __('Motorista') }}</label>
                    <select id="route-driver" wire:model="driverId" class="fleet-field">
                        <option value="">{{ __('Definir depois') }}</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                        @endforeach
                    </select>
                    @error('driverId') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="fleet-label" for="route-vehicle">{{ __('Veículo') }}</label>
                    <select id="route-vehicle" wire:model="vehicleId" class="fleet-field">
                        <option value="">{{ __('Definir depois') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->plate }} · {{ $vehicle->model }}</option>
                        @endforeach
                    </select>
                    @error('vehicleId') <p class="mt-1 text-xs text-fleet-danger">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="fleet-btn--primary fleet-btn--block" wire:loading.attr="disabled" wire:target="createRoute">
                    {{ __('Otimizar e criar rota') }}
                </button>
            </form>
        </div>
    </div>

    {{-- Rotas do dia --}}
    @if ($routes->isNotEmpty())
        <div class="overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-fleet">
            <div class="border-b border-fleet-border px-4 py-3">
                <p class="fleet-section-title">{{ __('Rotas do dia') }}</p>
            </div>
            <ul class="divide-y divide-fleet-border">
                @foreach ($routes as $route)
                    <li wire:key="route-{{ $route->id }}" class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-semibold text-fleet-ink">{{ $route->name }}</p>
                            <p class="text-xs text-fleet-secondary">
                                {{ $route->driver?->name ?? __('Sem motorista') }} · {{ $route->vehicle?->plate ?? __('sem veículo') }} ·
                                {{ trans_choice(':count parada|:count paradas', $route->stops_count) }} ·
                                {{ __(':km km estimados', ['km' => number_format(($route->total_distance_m ?? 0) / 1000, 1, ',', '.')]) }} ·
                                {{ $route->status->label() }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button type="button" wire:click="reoptimize({{ $route->id }})" class="fleet-btn--muted fleet-btn--sm">{{ __('Reotimizar') }}</button>
                            <a href="{{ route('deliveries.monitor', ['data' => $date, 'rota' => $route->id]) }}" wire:navigate class="fleet-btn--outline fleet-btn--sm">{{ __('Acompanhar') }}</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Pedidos --}}
    @php
        $pageRoutableIds = $deliveries->getCollection()
            ->filter(fn ($delivery) => $delivery->routeStop === null && $delivery->status === DeliveryStatus::Pending && $delivery->customer->hasCoordinates())
            ->pluck('id')
            ->values()
            ->all();
        $isPageSelected = $pageRoutableIds !== [] && array_diff($pageRoutableIds, $selected) === [];
        $hasFilters = $search !== '' || $sectorFilter !== '' || $statusFilter !== '';
        $sortableHeaders = ['order_number' => __('Pedido'), 'customer' => __('Cliente'), 'sector' => __('Setor')];
    @endphp
    <div class="overflow-hidden rounded-2xl border border-fleet-border bg-fleet-card shadow-fleet">
        <div class="flex flex-col gap-3 border-b border-fleet-border px-4 py-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <div>
                    <label class="fleet-label" for="delivery-search">{{ __('Buscar') }}</label>
                    <input id="delivery-search" type="search" wire:model.live.debounce.400ms="search" class="fleet-field" placeholder="{{ __('Nº do pedido, cliente, código ou endereço') }}" />
                </div>
                <div>
                    <label class="fleet-label" for="delivery-sector">{{ __('Setor') }}</label>
                    <select id="delivery-sector" wire:model.live="sectorFilter" class="fleet-field">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach ($sectors as $sector)
                            <option value="{{ $sector['sector'] !== '' ? $sector['sector'] : \App\Livewire\Deliveries\DeliveryPlanner::WITHOUT_SECTOR }}">{{ $sector['sector'] !== '' ? $sector['sector'] : __('Sem setor') }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="fleet-label" for="delivery-status">{{ __('Situação') }}</label>
                    <select id="delivery-status" wire:model.live="statusFilter" class="fleet-field">
                        <option value="">{{ __('Todas') }}</option>
                        @foreach ($statusOptions as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex items-end gap-3">
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters" class="fleet-btn--muted fleet-btn--sm">{{ __('Limpar filtros') }}</button>
                @endif
                <div>
                    <label class="fleet-label" for="delivery-per-page">{{ __('Por página') }}</label>
                    <select id="delivery-per-page" wire:model.live="perPage" class="fleet-field">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto" wire:loading.class="opacity-60" wire:target="search,sectorFilter,statusFilter,perPage,sortBy,gotoPage,nextPage,previousPage,clearFilters">
            <table class="min-w-full divide-y divide-fleet-border text-fleet-body">
                <thead class="fleet-table-head">
                    <tr>
                        <th class="w-10 px-4 py-3">
                            @if ($pageRoutableIds !== [])
                                <input type="checkbox" @checked($isPageSelected) wire:click="togglePageSelection(@js($pageRoutableIds))" class="rounded border-fleet-border text-fleet-primary focus:ring-fleet-primary/30" aria-label="{{ __('Selecionar pedidos desta página') }}" />
                            @endif
                        </th>
                        @foreach ($sortableHeaders as $field => $label)
                            <th class="px-4 py-3" aria-sort="{{ $sortField === $field ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <button type="button" wire:click="sortBy('{{ $field }}')" class="inline-flex items-center gap-1 uppercase hover:text-fleet-ink">
                                    {{ $label }}
                                    <span class="text-[10px]">{{ $sortField === $field ? ($sortDirection === 'asc' ? '▲' : '▼') : '↕' }}</span>
                                </button>
                            </th>
                        @endforeach
                        <th class="px-4 py-3">{{ __('Localização') }}</th>
                        <th class="px-4 py-3" aria-sort="{{ $sortField === 'status' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <button type="button" wire:click="sortBy('status')" class="inline-flex items-center gap-1 uppercase hover:text-fleet-ink">
                                {{ __('Situação') }}
                                <span class="text-[10px]">{{ $sortField === 'status' ? ($sortDirection === 'asc' ? '▲' : '▼') : '↕' }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-right" aria-sort="{{ $sortField === 'amount' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <button type="button" wire:click="sortBy('amount')" class="inline-flex items-center gap-1 uppercase hover:text-fleet-ink">
                                {{ __('Valor') }}
                                <span class="text-[10px]">{{ $sortField === 'amount' ? ($sortDirection === 'asc' ? '▲' : '▼') : '↕' }}</span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-fleet-border">
                    @forelse ($deliveries as $delivery)
                        @php
                            $customer = $delivery->customer;
                            $routable = $delivery->routeStop === null && $delivery->status === DeliveryStatus::Pending && $customer->hasCoordinates();
                        @endphp
                        <tr wire:key="delivery-{{ $delivery->id }}">
                            <td class="px-4 py-3">
                                @if ($routable)
                                    <input type="checkbox" value="{{ $delivery->id }}" wire:model.live="selected" class="rounded border-fleet-border text-fleet-primary focus:ring-fleet-primary/30" aria-label="{{ __('Selecionar pedido :order', ['order' => $delivery->order_number]) }}" />
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-fleet-ink">{{ $delivery->order_number }}</td>
                            <td class="max-w-xs px-4 py-3">
                                <p class="truncate font-medium text-fleet-ink" title="{{ $customer->name }}">{{ $customer->name }}</p>
                                <p class="truncate text-xs text-fleet-secondary" title="{{ $customer->fullAddress() }}">{{ $customer->external_code }} · {{ $customer->fullAddress() }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-fleet-secondary">{{ $delivery->sector ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <x-fleet.status-pill :color="$customer->geocode_status->color()" :label="$customer->geocode_status->label()" />
                                @if ($customer->geocode_error)
                                    <p class="mt-1 max-w-[16rem] text-xs text-fleet-danger">{{ $customer->geocode_error }}</p>
                                @endif
                                @if (in_array($customer->geocode_status, [GeocodeStatus::Failed, GeocodeStatus::Pending], true) && $googleKeyConfigured)
                                    <button type="button" wire:click="retryGeocoding({{ $customer->id }})" class="mt-1 text-xs font-semibold text-fleet-primary hover:underline">{{ __('Tentar de novo') }}</button>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <x-fleet.status-pill :color="$delivery->status->color()" :label="$delivery->status->label()" />
                                @if ($delivery->routeStop)
                                    <p class="mt-1 text-xs text-fleet-secondary">{{ $delivery->routeStop->deliveryRoute->name }} · #{{ $delivery->routeStop->sequence }}</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium text-fleet-ink">{{ format_money_brl($delivery->amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-fleet-muted">
                                @if ($hasFilters)
                                    {{ __('Nenhum pedido encontrado com esses filtros.') }}
                                @else
                                    {{ __('Nenhum pedido para esta data. Importe a planilha do dia para começar.') }}
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($deliveries->hasPages())
            <div class="border-t border-fleet-border px-4 py-3">
                {{ $deliveries->links() }}
            </div>
        @endif
    </div>
</div>
