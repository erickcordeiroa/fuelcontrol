<?php

namespace App\Services\Routing;

use App\Models\RoadLeg;
use App\Services\Geocoding\GeocoderFactory;
use App\Support\Polyline;

/**
 * Planeja a rota em duas partes, cada uma pelo caminho mais barato que serve:
 *
 * 1. Ordem: da rota base (encaixando as paradas novas aqui, sem custo), ou pelo Route Planner do Geoapify
 *    quando a conta da plataforma está configurada e o dia tem créditos, ou aqui em linha reta (RouteOptimizer).
 * 2. Trechos pelas ruas: reaproveita os já traçados (road_legs) e só pede ao Geoapify os que faltam.
 *
 * Sem crédito para o Route Planner, a ordem é calculada aqui e os trechos ainda saem pelas ruas. Se o serviço
 * falhar ou faltar crédito até para os trechos, a rota sai em linha reta. O motivo vai em RoutePlan::$note.
 */
class RoutePlanningService
{
    public function __construct(
        private readonly RouteOptimizer $optimizer,
        private readonly GeocoderFactory $quota,
    ) {}

    public function roadRoutingAvailable(): bool
    {
        return config('services.routing.provider') === RoutePlan::PROVIDER_GEOAPIFY
            && filled(config('services.geocoding.geoapify_key'));
    }

    /**
     * @param  array{lat: float, lng: float}|null  $start  Centro de distribuição ou posição atual do motorista
     * @param  array<int|string, array{lat: float, lng: float}>  $points  Paradas indexadas pelo id
     * @param  array{lat: float, lng: float}|null  $end  Volta ao centro de distribuição no fim
     * @param  list<int|string>|null  $baseOrder  Ordem já conhecida (ids de $points); as paradas fora dela são encaixadas
     */
    public function plan(?array $start, array $points, ?array $end, ?int $tenantId, ?array $baseOrder = null): RoutePlan
    {
        if ($points === []) {
            return new RoutePlan([], [], null, RoutePlan::PROVIDER_LOCAL);
        }

        $end = $start === null ? null : $end;

        [$order, $orderSource, $note] = $baseOrder !== null
            ? [$this->insertIntoOrder($start, $points, $end, $baseOrder), RoutePlan::ORDER_BASE, null]
            : $this->freshOrder($start, $points, $end, $tenantId);

        $roadLegs = $this->roadRoutingAvailable()
            ? $this->roadLegs($this->waypoints($start, $points, $end, $order), $tenantId, $note)
            : null;

        if ($roadLegs === null) {
            return $this->straightPlan($start, $points, $end, $order, $orderSource, $note);
        }

        if ($start === null) {
            array_unshift($roadLegs, new RouteLeg(0, 0, []));
        }

        return new RoutePlan(
            order: $order,
            legs: array_slice($roadLegs, 0, count($order)),
            returnLeg: $end !== null ? $roadLegs[count($order)] : null,
            provider: RoutePlan::PROVIDER_GEOAPIFY,
            orderSource: $orderSource,
            note: $note,
        );
    }

    /**
     * Trajeto pelas ruas de um ponto a outro, para o GPS do app.
     *
     * @param  array{lat: float, lng: float}  $from
     * @param  array{lat: float, lng: float}  $to
     *
     * @throws RoutingException
     */
    public function directions(array $from, array $to, ?int $tenantId): RouteLeg
    {
        if (! $this->roadRoutingAvailable()) {
            throw new RoutingException(__('Serviço de rotas não configurado na plataforma.'));
        }

        if (! $this->quota->hasDailyCapacity()) {
            throw new RoutingException(__('Limite diário do serviço de rotas atingido. Siga o trajeto já traçado.'));
        }

        return $this->geoapify()->directions($from, $to, $tenantId);
    }

    /**
     * Ordem nova: Route Planner quando o dia tem crédito para ele e para os trechos; senão, calculada aqui
     * (e os trechos ainda podem ser traçados pelas ruas, que custam bem menos).
     * Sem centro de distribuição, a primeira parada da ordem local vira o ponto de partida.
     *
     * @param  array{lat: float, lng: float}|null  $start
     * @param  array<int|string, array{lat: float, lng: float}>  $points
     * @param  array{lat: float, lng: float}|null  $end
     * @return array{0: list<int|string>, 1: string, 2: string|null} Ordem, origem da ordem e motivo de não usar o Route Planner
     */
    private function freshOrder(?array $start, array $points, ?array $end, ?int $tenantId): array
    {
        $localOrder = $this->optimizer->optimize($points, $start, $end !== null && $end === $start)['order'];

        if (! $this->wantsPlanner($start, $points)) {
            return [$localOrder, RoutePlan::ORDER_LOCAL, null];
        }

        $firstId = $start === null ? $localOrder[0] : null;
        $plannerStart = $start ?? $points[$firstId];
        $plannerPoints = $points;
        unset($plannerPoints[$firstId]);

        if (! $this->quota->hasDailyCapacity(GeoapifyRouting::creditsFor(count($plannerPoints), $end !== null))) {
            return [$localOrder, RoutePlan::ORDER_LOCAL, RoutePlan::NOTE_ORDER_DAILY_LIMIT];
        }

        try {
            $order = $this->geoapify()->optimizeOrder($plannerStart, $plannerPoints, $end, $tenantId);
        } catch (RoutingException $e) {
            report($e);

            return [$localOrder, RoutePlan::ORDER_LOCAL, RoutePlan::NOTE_SERVICE_FAILED];
        }

        return [$firstId === null ? $order : [$firstId, ...$order], RoutePlan::ORDER_PLANNER, null];
    }

    /**
     * O Route Planner só vale com duas ou mais paradas depois do ponto de partida (sem centro de distribuição,
     * a primeira parada é a partida).
     *
     * @param  array{lat: float, lng: float}|null  $start
     * @param  array<int|string, array{lat: float, lng: float}>  $points
     */
    private function wantsPlanner(?array $start, array $points): bool
    {
        return $this->roadRoutingAvailable() && count($points) - ($start === null ? 1 : 0) > 1;
    }

    /**
     * Mantém a ordem conhecida e encaixa cada parada nova onde ela aumenta menos o percurso (inserção mais barata).
     *
     * @param  array{lat: float, lng: float}|null  $start
     * @param  array<int|string, array{lat: float, lng: float}>  $points
     * @param  array{lat: float, lng: float}|null  $end
     * @param  list<int|string>  $baseOrder
     * @return list<int|string>
     */
    private function insertIntoOrder(?array $start, array $points, ?array $end, array $baseOrder): array
    {
        $order = array_values(array_unique(array_filter($baseOrder, fn (int|string $id): bool => isset($points[$id]))));
        $distance = fn (?array $a, ?array $b): float => $a === null || $b === null ? 0.0 : RouteOptimizer::haversineMeters($a, $b);

        foreach (array_diff(array_keys($points), $order) as $id) {
            $bestPosition = count($order);
            $bestCost = INF;

            for ($position = 0; $position <= count($order); $position++) {
                $previous = $position === 0 ? $start : $points[$order[$position - 1]];
                $next = $position === count($order) ? $end : $points[$order[$position]];
                $cost = $distance($previous, $points[$id]) + $distance($points[$id], $next) - $distance($previous, $next);

                if ($cost < $bestCost) {
                    $bestCost = $cost;
                    $bestPosition = $position;
                }
            }

            array_splice($order, $bestPosition, 0, [$id]);
        }

        return $order;
    }

    /**
     * Pontos por onde o trajeto passa: saída (ou a primeira parada), paradas na ordem e a chegada.
     *
     * @param  array{lat: float, lng: float}|null  $start
     * @param  array<int|string, array{lat: float, lng: float}>  $points
     * @param  array{lat: float, lng: float}|null  $end
     * @param  list<int|string>  $order
     * @return list<array{lat: float, lng: float}>
     */
    private function waypoints(?array $start, array $points, ?array $end, array $order): array
    {
        $waypoints = array_map(fn (int|string $id): array => $points[$id], $order);

        if ($start !== null) {
            array_unshift($waypoints, $start);
        }

        if ($end !== null) {
            $waypoints[] = $end;
        }

        return $waypoints;
    }

    /**
     * Um trecho por par de pontos: os já traçados vêm de road_legs; os que faltam são pedidos ao Geoapify,
     * agrupados em sequências contínuas (1 crédito por trecho). Null quando não dá para traçar todos.
     *
     * @param  list<array{lat: float, lng: float}>  $waypoints
     * @param  string|null  $note  Recebe o motivo quando não dá para traçar (limite do dia ou falha do serviço)
     * @return list<RouteLeg>|null
     */
    private function roadLegs(array $waypoints, ?int $tenantId, ?string &$note): ?array
    {
        if (count($waypoints) < 2) {
            return [];
        }

        $keys = [];
        for ($index = 0; $index < count($waypoints) - 1; $index++) {
            $keys[$index] = RoadLeg::keyFor($waypoints[$index], $waypoints[$index + 1]);
        }

        $cached = RoadLeg::query()->whereIn('leg_key', array_unique($keys))->get()->keyBy('leg_key');
        $missing = array_keys(array_filter($keys, fn (string $key): bool => ! $cached->has($key)));

        if ($missing !== [] && ! $this->quota->hasDailyCapacity(count($missing))) {
            $note = RoutePlan::NOTE_DAILY_LIMIT;

            return null;
        }

        try {
            foreach ($this->contiguousRuns($missing) as [$from, $to]) {
                $fetched = $this->geoapify()->legs(array_slice($waypoints, $from, $to - $from + 2), $tenantId);

                foreach ($fetched as $offset => $leg) {
                    $cached[$keys[$from + $offset]] = $this->remember($keys[$from + $offset], $leg);
                }
            }
        } catch (RoutingException $e) {
            report($e);
            $note = RoutePlan::NOTE_SERVICE_FAILED;

            return null;
        }

        RoadLeg::query()->whereIn('leg_key', array_unique($keys))->update(['last_used_at' => now()]);

        return array_map(fn (string $key): RouteLeg => new RouteLeg(
            distanceM: $cached[$key]->distance_m,
            durationS: $cached[$key]->duration_s,
            path: $cached[$key]->path_polyline === null ? [] : Polyline::decode($cached[$key]->path_polyline),
            steps: $cached[$key]->navigation_steps ?? [],
        ), array_values($keys));
    }

    /**
     * [0, 1, 2, 5, 7, 8] → [[0, 2], [5, 5], [7, 8]]
     *
     * @param  list<int>  $indexes
     * @return list<array{0: int, 1: int}>
     */
    private function contiguousRuns(array $indexes): array
    {
        $runs = [];

        foreach ($indexes as $index) {
            if ($runs !== [] && $runs[count($runs) - 1][1] === $index - 1) {
                $runs[count($runs) - 1][1] = $index;
            } else {
                $runs[] = [$index, $index];
            }
        }

        return $runs;
    }

    private function remember(string $key, RouteLeg $leg): RoadLeg
    {
        return RoadLeg::query()->updateOrCreate(['leg_key' => $key], [
            'distance_m' => $leg->distanceM,
            'duration_s' => $leg->durationS,
            'path_polyline' => $leg->path === [] ? null : Polyline::encode($leg->path),
            'navigation_steps' => $leg->steps,
            'last_used_at' => now(),
        ]);
    }

    /**
     * @param  array{lat: float, lng: float}|null  $start
     * @param  array<int|string, array{lat: float, lng: float}>  $points
     * @param  array{lat: float, lng: float}|null  $end
     * @param  list<int|string>  $order
     */
    private function straightPlan(?array $start, array $points, ?array $end, array $order, string $orderSource, ?string $note): RoutePlan
    {
        $legs = [];
        $previous = $start;

        foreach ($order as $id) {
            $point = $points[$id];
            $legs[] = $previous === null
                ? new RouteLeg(0, null, [])
                : new RouteLeg(RouteOptimizer::roadMeters(RouteOptimizer::haversineMeters($previous, $point)), null, [[$previous['lat'], $previous['lng']], [$point['lat'], $point['lng']]]);
            $previous = $point;
        }

        $returnLeg = null;
        if ($end !== null && $previous !== null) {
            $returnLeg = new RouteLeg(
                RouteOptimizer::roadMeters(RouteOptimizer::haversineMeters($previous, $end)),
                null,
                [[$previous['lat'], $previous['lng']], [$end['lat'], $end['lng']]],
            );
        }

        return new RoutePlan($order, $legs, $returnLeg, RoutePlan::PROVIDER_LOCAL, $orderSource, $note);
    }

    private function geoapify(): GeoapifyRouting
    {
        return new GeoapifyRouting(config('services.geocoding.geoapify_key'));
    }
}
