<?php

namespace App\Livewire\Deliveries;

use App\Enums\DeliveryStatus;
use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeCustomer;
use App\Livewire\Concerns\ChecksPlanLimits;
use App\Livewire\Concerns\KeepsMessages;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryImport;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Services\Deliveries\DeliveryImporter;
use App\Services\Geocoding\GeocoderFactory;
use App\Services\Routing\DeliveryRoutePlanner;
use App\Services\Routing\RouteMapPresenter;
use App\Services\Routing\RoutePlan;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Entregas')]
class DeliveryPlanner extends Component
{
    use ChecksPlanLimits, KeepsMessages, WithFileUploads;
    use WithPagination;

    /** Valor do filtro de setor que representa pedidos sem setor. */
    public const WITHOUT_SECTOR = '~';

    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [15, 30, 50, 100];

    /** @var list<string> */
    private const SORTABLE_FIELDS = ['order_number', 'customer', 'sector', 'status', 'amount'];

    #[Url(as: 'data')]
    public string $date = '';

    /** @var TemporaryUploadedFile|null */
    public $spreadsheet = null;

    public ?int $lastImportId = null;

    /** @var list<int> */
    public array $selected = [];

    public string $routeName = '';

    public ?int $driverId = null;

    public ?int $vehicleId = null;

    /** Ignora a rota base com o mesmo nome e otimiza a ordem de novo (gasta créditos do serviço de rotas). */
    public bool $optimizeFromScratch = false;

    /** Cliente com o endereço aberto para edição. */
    public ?int $editingCustomerId = null;

    /** @var array{street: string, number: string, district: string, city: string, state: string, zip_code: string, reference: string} */
    public array $addressForm = [
        'street' => '',
        'number' => '',
        'district' => '',
        'city' => '',
        'state' => '',
        'zip_code' => '',
        'reference' => '',
    ];

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    #[Url(as: 'setor', except: '')]
    public string $sectorFilter = '';

    #[Url(as: 'situacao', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'ordem', except: 'sector')]
    public string $sortField = 'sector';

    #[Url(as: 'direcao', except: 'asc')]
    public string $sortDirection = 'asc';

    #[Url(as: 'por_pagina', except: 15)]
    public int $perPage = 15;

    public function mount(): void
    {
        Gate::authorize('create', DeliveryRoute::class);

        if ($this->date === '' || ! $this->isValidDate($this->date)) {
            $this->date = now()->toDateString();
        }
    }

    /**
     * Centro de distribuição salvo: redesenha o mapa com o ponto de saída.
     */
    #[On('depot-updated')]
    public function refreshDepot(): void {}

    public function updatedDate(): void
    {
        if (! $this->isValidDate($this->date)) {
            $this->date = now()->toDateString();
        }

        $this->selected = [];
        $this->lastImportId = null;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSectorFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = self::PER_PAGE_OPTIONS[0];
        }

        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE_FIELDS, true)) {
            return;
        }

        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'sectorFilter', 'statusFilter');
        $this->resetPage();
    }

    /**
     * Marca (ou desmarca, se todos já estiverem marcados) os pedidos roteirizáveis da página atual.
     *
     * @param  list<int>  $deliveryIds
     */
    public function togglePageSelection(array $deliveryIds): void
    {
        $routableIds = $this->routableDeliveries()->pluck('id')->intersect($deliveryIds)->values()->all();

        if ($routableIds === []) {
            return;
        }

        $this->selected = array_diff($routableIds, $this->selected) === []
            ? array_values(array_diff($this->selected, $routableIds))
            : array_values(array_unique([...$this->selected, ...$routableIds]));
    }

    public function import(DeliveryImporter $importer): void
    {
        Gate::authorize('create', DeliveryRoute::class);

        $this->validate([
            'spreadsheet' => ['required', 'file', 'extensions:xlsx,csv', 'max:10240'],
            'date' => ['required', 'date_format:Y-m-d'],
        ], attributes: ['spreadsheet' => __('planilha')]);

        $import = $importer->import(
            $this->spreadsheet->getRealPath(),
            $this->spreadsheet->getClientOriginalName(),
            Carbon::parse($this->date),
        );

        $this->lastImportId = $import->id;
        $this->reset('spreadsheet');

        $this->notify(__(':imported de :total pedido(s) importado(s).', [
            'imported' => $import->imported_rows,
            'total' => $import->total_rows,
        ]));
    }

    /**
     * Seleciona os pedidos sem rota (e com localização) de um setor.
     */
    public function selectSector(string $sector): void
    {
        $this->selected = $this->routableDeliveries()
            ->filter(fn (Delivery $delivery): bool => ($delivery->sector ?? '') === $sector)
            ->pluck('id')
            ->all();

        $this->routeName = $sector !== '' ? __('Setor :sector', ['sector' => $sector]) : __('Sem setor');
        $this->resetValidation();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function createRoute(DeliveryRoutePlanner $planner): void
    {
        Gate::authorize('create', DeliveryRoute::class);

        if (! $this->planAllowsAdding('routes_per_month')) {
            return;
        }

        $validated = $this->validate([
            'routeName' => ['required', 'string', 'max:120'],
            'driverId' => ['nullable', 'integer', Rule::exists('drivers', 'id')->where('user_id', auth()->user()->tenantOwnerId())],
            'vehicleId' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where('user_id', auth()->user()->tenantOwnerId())],
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer'],
        ], attributes: [
            'routeName' => __('nome da rota'),
            'driverId' => __('motorista'),
            'vehicleId' => __('veículo'),
            'selected' => __('pedidos'),
        ]);

        $deliveries = Delivery::query()
            ->unrouted()
            ->whereDate('scheduled_for', $this->date)
            ->whereKey($validated['selected'])
            ->with('customer')
            ->get();

        try {
            $route = $planner->create([
                'name' => $validated['routeName'],
                'scheduled_for' => $this->date,
                'driver_id' => $validated['driverId'],
                'vehicle_id' => $validated['vehicleId'],
            ], $deliveries, $this->optimizeFromScratch);
        } catch (InvalidArgumentException $e) {
            $this->addError('selected', $e->getMessage());

            return;
        }

        session()->flash('status', __('Rota ":name" criada com :count parada(s).', ['name' => $route->name, 'count' => $deliveries->count()]).$this->orderSourceNotice($route).$this->straightLineNotice($route));

        $this->redirectRoute('deliveries.monitor', ['data' => $this->date, 'rota' => $route->id], navigate: true);
    }

    /**
     * Pedidos selecionados entram numa rota já criada: são encaixados na ordem atual, sem otimizar tudo de novo.
     */
    public function addToRoute(int $routeId, DeliveryRoutePlanner $planner): void
    {
        $route = DeliveryRoute::query()->findOrFail($routeId);
        Gate::authorize('update', $route);

        $deliveries = Delivery::query()
            ->unrouted()
            ->whereDate('scheduled_for', $route->scheduled_for->toDateString())
            ->whereKey($this->selected)
            ->with('customer')
            ->get();

        try {
            $route = $planner->addDeliveries($route, $deliveries);
        } catch (InvalidArgumentException $e) {
            $this->addError('selected', $e->getMessage());

            return;
        }

        $this->selected = [];

        $this->notify(__(':count pedido(s) incluído(s) na rota ":name", encaixado(s) na ordem atual. Só os trechos novos foram traçados.', ['name' => $route->name, 'count' => $deliveries->count()]).$this->straightLineNotice($route));
    }

    /**
     * Só otimiza de novo quando algo mudou (cliente com nova localização ou rota traçada em linha reta):
     * o trajeto salvo na criação continua valendo e cada nova otimização gasta créditos.
     */
    public function reoptimize(int $routeId, DeliveryRoutePlanner $planner): void
    {
        $route = DeliveryRoute::query()->with('stops.delivery.customer')->findOrFail($routeId);
        Gate::authorize('update', $route);

        if ($planner->reoptimizationReason($route) === null) {
            $this->notify(__('A rota ":name" já está otimizada. Nada mudou desde o último cálculo.', ['name' => $route->name]));

            return;
        }

        try {
            $route = $planner->reoptimize($route);
        } catch (InvalidArgumentException $e) {
            $this->notify($e->getMessage());

            return;
        }

        $this->notify(__('Rota ":name" reotimizada.', ['name' => $route->name]).$this->straightLineNotice($route));
    }

    private function orderSourceNotice(DeliveryRoute $route): string
    {
        return match ($route->order_source) {
            RoutePlan::ORDER_BASE => ' '.__('A ordem seguiu a rota base, sem nova otimização.'),
            RoutePlan::ORDER_PLANNER => ' '.__('A ordem foi otimizada e salva como rota base para as próximas vezes.'),
            default => '',
        };
    }

    /**
     * Avisa quando a rota não saiu otimizada ou pelas ruas, com o motivo (limite do dia ou falha do serviço).
     */
    private function straightLineNotice(DeliveryRoute $route): string
    {
        $notice = $route->routingNotice();

        return $notice === null ? '' : ' '.$notice;
    }

    /**
     * Admin arrastou o pino no mapa: a posição passa a ser própria (permanente).
     */
    public function moveCustomer(int $customerId, float $latitude, float $longitude): void
    {
        Gate::authorize('create', DeliveryRoute::class);

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return;
        }

        $customer = Customer::query()->findOrFail($customerId);
        $customer->confirmLocation($latitude, $longitude, GeocodeStatus::Manual);
    }

    /**
     * Exclui uma rota criada por engano. Pedidos voltam para "Pendente"; rota com baixas fica, para não perder o histórico.
     */
    public function deleteRoute(int $routeId): void
    {
        $route = DeliveryRoute::query()->with('stops.delivery')->findOrFail($routeId);
        Gate::authorize('delete', $route);

        if ($route->stops->contains(fn ($stop): bool => $stop->delivery->status->isFinished())) {
            $this->notifyError(__('A rota ":name" tem entregas baixadas e não pode ser excluída.', ['name' => $route->name]));

            return;
        }

        DB::transaction(function () use ($route): void {
            foreach ($route->stops as $stop) {
                $stop->delivery->update(['status' => DeliveryStatus::Pending]);
            }

            $route->delete();
        });

        $this->selected = [];
        $this->notify(__('Rota ":name" excluída. Os pedidos voltaram para "Pendente".', ['name' => $route->name]));
    }

    public function openAddressEditor(int $customerId): void
    {
        Gate::authorize('create', DeliveryRoute::class);

        $customer = Customer::query()->findOrFail($customerId);

        $this->editingCustomerId = $customer->id;
        $this->addressForm = [
            'street' => (string) $customer->street,
            'number' => (string) $customer->number,
            'district' => (string) $customer->district,
            'city' => (string) $customer->city,
            'state' => (string) $customer->state,
            'zip_code' => (string) $customer->zip_code,
            'reference' => (string) $customer->reference,
        ];
        $this->resetValidation();
    }

    public function closeAddressEditor(): void
    {
        $this->editingCustomerId = null;
        $this->resetValidation();
    }

    /**
     * Salva o endereço; se mudou, descarta a localização antiga e refaz a busca (base de endereços primeiro).
     */
    public function saveAddress(): void
    {
        Gate::authorize('create', DeliveryRoute::class);

        $this->addressForm['zip_code'] = preg_replace('/\D+/', '', $this->addressForm['zip_code']) ?? '';
        $this->addressForm['state'] = mb_strtoupper(trim($this->addressForm['state']));

        $validated = $this->validate([
            'addressForm.street' => ['required', 'string', 'max:255'],
            'addressForm.number' => ['nullable', 'string', 'max:20'],
            'addressForm.district' => ['nullable', 'string', 'max:120'],
            'addressForm.city' => ['required', 'string', 'max:120'],
            'addressForm.state' => ['required', 'string', 'size:2', 'alpha'],
            'addressForm.zip_code' => ['required', 'digits:8'],
            'addressForm.reference' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'addressForm.street' => __('rua'),
            'addressForm.number' => __('número'),
            'addressForm.district' => __('bairro'),
            'addressForm.city' => __('cidade'),
            'addressForm.state' => __('UF'),
            'addressForm.zip_code' => __('CEP'),
            'addressForm.reference' => __('referência'),
        ])['addressForm'];

        $customer = Customer::query()->findOrFail($this->editingCustomerId);

        $address = [
            'street' => trim($validated['street']),
            'number' => filled($validated['number']) ? trim($validated['number']) : null,
            'district' => filled($validated['district']) ? trim($validated['district']) : null,
            'city' => trim($validated['city']),
            'state' => $validated['state'],
            'zip_code' => $validated['zip_code'],
        ];
        $addressHash = Customer::hashAddress($address);
        $addressChanged = $addressHash !== $customer->address_hash;

        if ($addressChanged) {
            $customer->resetLocation();
        }

        $customer->fill([
            ...$address,
            'reference' => filled($validated['reference']) ? trim($validated['reference']) : null,
            'address_hash' => $addressHash,
        ])->save();

        $this->editingCustomerId = null;

        if (! $addressChanged) {
            $this->notify(__('Dados do cliente atualizados.'));

            return;
        }

        GeocodeCustomer::dispatch($customer);

        $isInRoute = Delivery::query()->where('customer_id', $customer->id)->whereHas('routeStop')->whereIn('status', [DeliveryStatus::Routed, DeliveryStatus::InTransit])->exists();

        $this->notify($isInRoute
            ? __('Endereço atualizado e localização sendo buscada de novo. O cliente está numa rota: reotimize a rota depois que ele for localizado.')
            : __('Endereço atualizado. A localização está sendo buscada de novo.'));
    }

    public function retryGeocoding(int $customerId): void
    {
        Gate::authorize('create', DeliveryRoute::class);

        $customer = Customer::query()->findOrFail($customerId);
        $customer->forceFill(['geocode_status' => GeocodeStatus::Pending, 'geocode_error' => null])->save();

        GeocodeCustomer::dispatch($customer);
    }

    public function render(): View
    {
        $presenter = app(RouteMapPresenter::class);

        $deliveries = Delivery::query()
            ->whereDate('scheduled_for', $this->date)
            ->with(['customer', 'routeStop.deliveryRoute'])
            ->orderBy('sector')
            ->orderBy('id')
            ->get();

        $planner = app(DeliveryRoutePlanner::class);
        $routes = DeliveryRoute::query()
            ->whereDate('scheduled_for', $this->date)
            ->with(['driver', 'vehicle', 'stops.delivery.customer'])
            ->withCount('stops')
            ->orderBy('name')
            ->get();

        $mapPayload = $presenter->forPlanning($deliveries, 'planning-'.$this->date);
        $this->dispatch('route-map-update', payload: $mapPayload);

        return view('livewire.deliveries.delivery-planner', [
            'deliveries' => $this->paginatedDeliveries(),
            'statusOptions' => DeliveryStatus::cases(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'sectors' => $this->sectorSummary($deliveries),
            'stats' => [
                'total' => $deliveries->count(),
                'unrouted' => $deliveries->filter(fn (Delivery $d): bool => $d->routeStop === null && $d->status === DeliveryStatus::Pending)->count(),
                'needsReview' => $deliveries->filter(fn (Delivery $d): bool => $d->customer->geocode_status->needsReview())->count(),
                'waitingGeocode' => $deliveries->filter(fn (Delivery $d): bool => $d->customer->geocode_status === GeocodeStatus::Pending)->count(),
                'geocodeStalled' => $deliveries->filter(fn (Delivery $d): bool => $d->customer->geocode_status === GeocodeStatus::Pending
                    && $d->customer->geocode_error === null
                    && $d->customer->updated_at?->lt(now()->subMinutes(Customer::GEOCODE_STALE_MINUTES)))->count(),
            ],
            'routes' => $routes,
            'basePreview' => $this->basePreview($planner),
            'reoptimizationReasons' => $routes->mapWithKeys(fn (DeliveryRoute $route): array => [$route->id => $planner->reoptimizationReason($route)])->all(),
            'drivers' => Driver::query()->orderBy('name')->get(['id', 'name']),
            'vehicles' => Vehicle::query()->orderBy('plate')->get(['id', 'plate', 'model']),
            'lastImport' => $this->lastImportId ? DeliveryImport::query()->find($this->lastImportId) : null,
            'mapPayload' => $mapPayload,
            'geocodingAvailable' => app(GeocoderFactory::class)->isConfigured(),
        ]);
    }

    /**
     * Rota base com o nome digitado: quantos clientes ela já conhece entre os pedidos selecionados.
     *
     * @return array{customers: int, known: int, new: int, reusable: bool}|null
     */
    private function basePreview(DeliveryRoutePlanner $planner): ?array
    {
        if (trim($this->routeName) === '' || $this->selected === []) {
            return null;
        }

        $deliveries = Delivery::query()->whereKey($this->selected)->get(['id', 'customer_id']);

        return $deliveries->isEmpty() ? null : $planner->basePreview(auth()->user()->tenantOwnerId(), $this->routeName, $deliveries);
    }

    /**
     * Pedidos da tabela: busca, filtros e ordenação aplicados no banco.
     *
     * @return LengthAwarePaginator<int, Delivery>
     */
    private function paginatedDeliveries(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $status = DeliveryStatus::tryFrom($this->statusFilter);
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Delivery::query()
            ->whereDate('scheduled_for', $this->date)
            ->with(['customer', 'routeStop.deliveryRoute'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.$search.'%';

                $query->where(fn (Builder $query) => $query
                    ->where('order_number', 'like', $term)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer
                        ->where('name', 'like', $term)
                        ->orWhere('external_code', 'like', $term)
                        ->orWhere('street', 'like', $term)
                        ->orWhere('district', 'like', $term)));
            })
            ->when($this->sectorFilter === self::WITHOUT_SECTOR, fn (Builder $query) => $query->whereNull('sector'))
            ->when($this->sectorFilter !== '' && $this->sectorFilter !== self::WITHOUT_SECTOR, fn (Builder $query) => $query->where('sector', $this->sectorFilter))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when(
                $this->sortField === 'customer',
                fn (Builder $query) => $query->orderBy(
                    Customer::query()->select('name')->whereColumn('customers.id', 'deliveries.customer_id'),
                    $direction,
                ),
                fn (Builder $query) => $query->orderBy(in_array($this->sortField, self::SORTABLE_FIELDS, true) ? $this->sortField : 'sector', $direction),
            )
            ->orderBy('id')
            ->paginate($this->perPage);
    }

    /**
     * @param  Collection<int, Delivery>  $deliveries
     * @return Collection<int, array{sector: string, total: int, routable: int}>
     */
    private function sectorSummary(Collection $deliveries): Collection
    {
        return $deliveries
            ->groupBy(fn (Delivery $delivery): string => $delivery->sector ?? '')
            ->map(fn (Collection $group, string $sector): array => [
                'sector' => $sector,
                'total' => $group->count(),
                'routable' => $group->filter(fn (Delivery $d): bool => $this->isRoutable($d))->count(),
            ])
            ->values();
    }

    /**
     * @return Collection<int, Delivery>
     */
    private function routableDeliveries(): Collection
    {
        return Delivery::query()
            ->unrouted()
            ->whereDate('scheduled_for', $this->date)
            ->with('customer')
            ->get()
            ->filter(fn (Delivery $delivery): bool => $delivery->customer->hasCoordinates());
    }

    private function isRoutable(Delivery $delivery): bool
    {
        return $delivery->routeStop === null
            && $delivery->status === DeliveryStatus::Pending
            && $delivery->customer->hasCoordinates();
    }

    private function isValidDate(string $value): bool
    {
        return Carbon::canBeCreatedFromFormat($value, 'Y-m-d');
    }
}
