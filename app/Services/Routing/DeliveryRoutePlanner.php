<?php

namespace App\Services\Routing;

use App\Enums\DeliveryRouteStatus;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use App\Models\RouteTemplate;
use App\Support\Polyline;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeliveryRoutePlanner
{
    /**
     * Acima dessa fatia de clientes novos, encaixar na rota base deixa a ordem ruim: otimiza do zero.
     */
    public const BASE_MAX_NEW_SHARE = 0.2;

    public function __construct(private readonly RoutePlanningService $planning) {}

    /**
     * Cria a rota com as paradas ordenadas e marca os pedidos como roteirizados. Com uma rota base do mesmo nome,
     * segue a ordem dela e só encaixa os clientes novos, sem pagar uma nova otimização.
     *
     * @param  array{name: string, scheduled_for: string, driver_id?: int|null, vehicle_id?: int|null, user_id?: int|null, origin_latitude?: float|null, origin_longitude?: float|null}  $attributes
     * @param  Collection<int, Delivery>  $deliveries
     * @param  bool  $fromScratch  Ignora a rota base e otimiza de novo (a base passa a ser a ordem nova)
     */
    public function create(array $attributes, Collection $deliveries, bool $fromScratch = false): DeliveryRoute
    {
        if ($deliveries->isEmpty()) {
            throw new InvalidArgumentException(__('Selecione ao menos um pedido.'));
        }

        $deliveries = EloquentCollection::make($deliveries->values()->all())->loadMissing('customer');

        $this->ensureLocated($deliveries);

        $depot = DeliveryRoute::defaultOrigin($attributes['user_id'] ?? null);

        return DB::transaction(function () use ($attributes, $deliveries, $depot, $fromScratch): DeliveryRoute {
            $route = DeliveryRoute::query()->create([
                ...$attributes,
                'origin_latitude' => $attributes['origin_latitude'] ?? $depot['lat'] ?? null,
                'origin_longitude' => $attributes['origin_longitude'] ?? $depot['lng'] ?? null,
                'status' => DeliveryRouteStatus::Planned,
            ]);

            $template = $this->templateFor($route->user_id, $route->name);
            $baseOrder = $fromScratch ? null : $this->baseOrder($template, $deliveries);

            $this->sequence($route, $deliveries, $route->origin(), [], $baseOrder);
            $this->rememberTemplate($route, $template);

            return $route;
        });
    }

    /**
     * O que vai acontecer ao criar a rota com este nome e estes pedidos, para avisar na tela antes de criar.
     *
     * @param  Collection<int, Delivery>  $deliveries
     * @return array{customers: int, known: int, new: int, reusable: bool}|null Null quando não há rota base com esse nome
     */
    public function basePreview(int $tenantId, string $routeName, Collection $deliveries): ?array
    {
        $template = $this->templateFor($tenantId, $routeName);

        if ($template?->optimized_at === null) {
            return null;
        }

        $present = $deliveries->pluck('customer_id')->unique();
        $known = $present->intersect($template->customer_ids)->count();

        return [
            'customers' => count($template->customer_ids),
            'known' => $known,
            'new' => $present->count() - $known,
            'reusable' => $this->baseOrder($template, $deliveries) !== null,
        ];
    }

    /**
     * Recalcula a ordem das paradas ainda sem baixa. As baixadas ficam como estão, no início; com a rota
     * em andamento, o novo trajeto parte da última posição do motorista.
     */
    public function reoptimize(DeliveryRoute $route): DeliveryRoute
    {
        return $this->resequenceOpenStops($route, new Collection, keepOrder: false);
    }

    /**
     * Pedidos que entraram depois na rota: as paradas abertas mantêm a ordem e os novos são encaixados nela.
     * Só os trechos novos são traçados pelas ruas.
     *
     * @param  Collection<int, Delivery>  $deliveries
     */
    public function addDeliveries(DeliveryRoute $route, Collection $deliveries): DeliveryRoute
    {
        if ($deliveries->isEmpty()) {
            throw new InvalidArgumentException(__('Selecione ao menos um pedido.'));
        }

        if ($route->status === DeliveryRouteStatus::Completed) {
            throw new InvalidArgumentException(__('A rota ":name" já foi concluída.', ['name' => $route->name]));
        }

        $deliveries = EloquentCollection::make($deliveries->values()->all())->loadMissing('customer');
        $this->ensureLocated($deliveries);

        return $this->resequenceOpenStops($route, $deliveries, keepOrder: true);
    }

    /**
     * Por que vale otimizar a rota de novo, ou null quando o trajeto salvo continua valendo e reotimizar só gastaria créditos.
     */
    public function reoptimizationReason(DeliveryRoute $route): ?string
    {
        if ($route->status === DeliveryRouteStatus::Completed) {
            return null;
        }

        $open = $route->stops->reject(fn (RouteStop $stop): bool => $stop->delivery->status->isFinished());

        if ($open->isEmpty()) {
            return null;
        }

        $moved = $route->optimized_at !== null && $open->contains(function (RouteStop $stop) use ($route): bool {
            $customer = $stop->delivery->customer;
            $locatedAt = collect([$customer->confirmed_at, $customer->geocoded_at])->filter()->max();

            return $locatedAt !== null && $locatedAt->gt($route->optimized_at);
        });

        if ($moved) {
            return __('Localização de cliente mudou');
        }

        if (! $this->planning->roadRoutingAvailable()) {
            return null;
        }

        if (! $route->isRoutedByRoads()) {
            return __('Traçada em linha reta');
        }

        return $route->routing_note === RoutePlan::NOTE_ORDER_DAILY_LIMIT && $open->count() > 1
            ? __('Ordem não otimizada (limite do dia)')
            : null;
    }

    /**
     * @param  Collection<int, Delivery>  $added
     * @param  bool  $keepOrder  Mantém a ordem das paradas abertas (true) ou otimiza do zero (false)
     */
    private function resequenceOpenStops(DeliveryRoute $route, Collection $added, bool $keepOrder): DeliveryRoute
    {
        return DB::transaction(function () use ($route, $added, $keepOrder): DeliveryRoute {
            $route->load(['stops.delivery.customer', 'latestLocation']);

            [$finished, $open] = $route->stops->partition(fn (RouteStop $stop): bool => $stop->delivery->status->isFinished());

            $start = $this->restartPoint($route, $finished);
            $route->stops()->whereKey($open->modelKeys())->delete();

            $deliveries = $open->map(fn (RouteStop $stop): Delivery => $stop->delivery)->values()->concat($added->values());
            $this->ensureLocated($deliveries);

            $baseOrder = $keepOrder ? $open->sortBy('sequence')->pluck('delivery_id')->values()->all() : null;

            $this->sequence($route, $deliveries, $start, $finished->values()->all(), $baseOrder);
            $this->rememberTemplate($route, $this->templateFor($route->user_id, $route->name));

            return $route->refresh();
        });
    }

    /**
     * @param  Collection<int, Delivery>  $deliveries
     */
    private function ensureLocated(Collection $deliveries): void
    {
        $withoutLocation = $deliveries->filter(fn (Delivery $delivery): bool => ! $delivery->customer->hasCoordinates());

        if ($withoutLocation->isNotEmpty()) {
            throw new InvalidArgumentException(__('Há :count pedido(s) sem localização. Revise os endereços antes de criar a rota.', [
                'count' => $withoutLocation->count(),
            ]));
        }
    }

    /**
     * @param  Collection<int, Delivery>  $deliveries  Pedidos a ordenar
     * @param  array{lat: float, lng: float}|null  $start
     * @param  list<RouteStop>  $keptStops  Paradas já baixadas, mantidas no início
     * @param  list<int>|null  $baseOrder  Ids dos pedidos na ordem a manter; null otimiza do zero
     */
    private function sequence(DeliveryRoute $route, Collection $deliveries, ?array $start, array $keptStops, ?array $baseOrder = null): void
    {
        $points = $deliveries
            ->mapWithKeys(fn (Delivery $delivery): array => [$delivery->id => $delivery->customer->coordinates()])
            ->all();

        $plan = $this->planning->plan($start, $points, $route->origin(), $route->user_id, $baseOrder);

        $sequence = 0;
        foreach ($keptStops as $stop) {
            $stop->update(['sequence' => ++$sequence]);
        }

        $byId = $deliveries->keyBy('id');
        foreach ($plan->order as $index => $deliveryId) {
            $leg = $plan->legs[$index];

            $route->stops()->create([
                'delivery_id' => $deliveryId,
                'sequence' => ++$sequence,
                'distance_from_previous_m' => $leg->distanceM,
                'duration_from_previous_s' => $leg->durationS,
                'path_polyline' => $leg->path === [] ? null : Polyline::encode($leg->path),
                'navigation_steps' => $leg->steps === [] ? null : $leg->steps,
            ]);

            $delivery = $byId[$deliveryId];
            if ($delivery->status === DeliveryStatus::Pending) {
                $delivery->update(['status' => DeliveryStatus::Routed]);
            }
        }

        $kept = collect($keptStops);

        $route->forceFill([
            'total_distance_m' => $kept->sum('distance_from_previous_m') + $plan->totalDistanceM(),
            'total_duration_s' => $plan->totalDurationS() === null ? null : $kept->sum('duration_from_previous_s') + $plan->totalDurationS(),
            'routing_provider' => $plan->provider,
            'order_source' => $plan->orderSource,
            'routing_note' => $plan->note,
            'return_path_polyline' => $plan->returnLeg === null || $plan->returnLeg->path === [] ? null : Polyline::encode($plan->returnLeg->path),
            'optimized_at' => now(),
        ])->save();
    }

    private function templateFor(int $tenantId, string $routeName): ?RouteTemplate
    {
        return RouteTemplate::query()->withoutGlobalScopes()->where('user_id', $tenantId)->named($routeName)->first();
    }

    /**
     * Pedidos na ordem da rota base (pedidos do mesmo cliente ficam juntos), ou null quando a base não serve:
     * não existe, nunca foi otimizada pelas ruas ou há clientes novos demais.
     *
     * @param  Collection<int, Delivery>  $deliveries
     * @return list<int>|null
     */
    private function baseOrder(?RouteTemplate $template, Collection $deliveries): ?array
    {
        if ($template?->optimized_at === null) {
            return null;
        }

        $byCustomer = $deliveries->groupBy('customer_id');
        $known = array_values(array_filter($template->customer_ids, fn (int $customerId): bool => $byCustomer->has($customerId)));
        $newShare = 1 - count($known) / $byCustomer->count();

        if ($known === [] || $newShare > self::BASE_MAX_NEW_SHARE) {
            return null;
        }

        return collect($known)->flatMap(fn (int $customerId) => $byCustomer[$customerId]->pluck('id'))->values()->all();
    }

    /**
     * Guarda a ordem dos clientes desta rota como rota base do nome dela. Clientes da base que não vieram desta vez
     * continuam guardados, encaixados depois do cliente que vinha antes deles.
     */
    private function rememberTemplate(DeliveryRoute $route, ?RouteTemplate $template): void
    {
        $routeOrder = $route->stops()->orderBy('sequence')->with('delivery:id,customer_id')->get()
            ->map(fn (RouteStop $stop): int => $stop->delivery->customer_id)
            ->unique()
            ->values()
            ->all();

        $baseOrder = $template === null ? [] : $template->customer_ids;
        $keptBase = $route->order_source === RoutePlan::ORDER_BASE;

        $template ??= new RouteTemplate(['user_id' => $route->user_id]);
        $template->fill([
            'name' => $route->name,
            'name_key' => RouteTemplate::keyFor($route->name),
            'customer_ids' => $keptBase ? self::mergeOrders($baseOrder, $routeOrder) : self::mergeOrders($routeOrder, $baseOrder),
            'optimized_at' => $route->order_source === RoutePlan::ORDER_PLANNER ? now() : $template->optimized_at,
        ])->save();
    }

    /**
     * Junta duas ordens: a principal manda; cada item que só existe na outra entra logo depois do item que o
     * antecede nela. mergeOrders([1, 2, 3], [1, 9, 3]) → [1, 9, 2, 3].
     *
     * @param  list<int>  $primary
     * @param  list<int>  $secondary
     * @return list<int>
     */
    public static function mergeOrders(array $primary, array $secondary): array
    {
        $merged = array_values($primary);
        $anchor = -1;

        foreach ($secondary as $item) {
            $position = array_search($item, $merged, true);

            if ($position !== false) {
                $anchor = $position;

                continue;
            }

            array_splice($merged, ++$anchor, 0, [$item]);
        }

        return $merged;
    }

    /**
     * Ponto de onde o restante da rota parte: posição do motorista, última parada baixada ou o centro de distribuição.
     *
     * @param  Collection<int, RouteStop>  $finished
     * @return array{lat: float, lng: float}|null
     */
    private function restartPoint(DeliveryRoute $route, Collection $finished): ?array
    {
        if ($route->status === DeliveryRouteStatus::InProgress && $route->latestLocation !== null) {
            return ['lat' => $route->latestLocation->latitude, 'lng' => $route->latestLocation->longitude];
        }

        if ($finished->isNotEmpty()) {
            return $finished->last()->delivery->customer->coordinates();
        }

        return $route->origin();
    }
}
