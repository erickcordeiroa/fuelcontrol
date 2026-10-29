<?php

namespace App\Services\Routing;

/**
 * Ordena paradas pelo menor percurso (problema do caixeiro-viajante) sem custo de API:
 * vizinho mais próximo para a solução inicial e 2-opt para desfazer cruzamentos.
 *
 * A distância é Haversine (linha reta na esfera). Para estimar o percurso por ruas,
 * a distância em linha reta é multiplicada por ROAD_FACTOR.
 */
class RouteOptimizer
{
    private const EARTH_RADIUS_M = 6_371_000;

    /**
     * Fator médio entre linha reta e percurso real em malha urbana.
     */
    public const ROAD_FACTOR = 1.3;

    /**
     * @param  array<int|string, array{lat: float, lng: float}>  $points  Paradas indexadas pelo id
     * @param  array{lat: float, lng: float}|null  $origin  Ponto de partida (centro de distribuição)
     * @param  bool  $returnToOrigin  Considera a volta à origem no cálculo (só com origem)
     * @return array{order: list<int|string>, legs_m: list<int>, total_m: int}
     */
    public function optimize(array $points, ?array $origin = null, bool $returnToOrigin = true): array
    {
        if ($points === []) {
            return ['order' => [], 'legs_m' => [], 'total_m' => 0];
        }

        $keys = array_keys($points);
        $nodes = array_values($points);
        $hasOrigin = $origin !== null;
        $closed = $hasOrigin && $returnToOrigin;

        if ($hasOrigin) {
            array_unshift($nodes, $origin);
        }

        $matrix = $this->distanceMatrix($nodes);
        $start = $hasOrigin ? 0 : $this->farthestFromCentroid($nodes);

        $path = $this->nearestNeighbor($matrix, $start);
        $path = $this->twoOpt($matrix, $path, $closed);

        $stopPath = $hasOrigin ? array_slice($path, 1) : $path;
        $order = array_map(fn (int $node): int|string => $keys[$hasOrigin ? $node - 1 : $node], $stopPath);

        $legs = [];
        $previous = $hasOrigin ? 0 : null;
        foreach ($stopPath as $node) {
            $legs[] = $previous === null ? 0 : $this->roadMeters($matrix[$previous][$node]);
            $previous = $node;
        }

        $total = array_sum($legs);
        if ($closed) {
            $total += $this->roadMeters($matrix[$previous][0]);
        }

        return ['order' => $order, 'legs_m' => $legs, 'total_m' => $total];
    }

    /**
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     */
    public static function haversineMeters(array $a, array $b): float
    {
        $lat1 = deg2rad($a['lat']);
        $lat2 = deg2rad($b['lat']);
        $deltaLat = $lat2 - $lat1;
        $deltaLng = deg2rad($b['lng'] - $a['lng']);

        $h = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($h)));
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $nodes
     * @return list<list<float>>
     */
    private function distanceMatrix(array $nodes): array
    {
        $count = count($nodes);
        $matrix = array_fill(0, $count, array_fill(0, $count, 0.0));

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $matrix[$i][$j] = $matrix[$j][$i] = self::haversineMeters($nodes[$i], $nodes[$j]);
            }
        }

        return $matrix;
    }

    /**
     * Sem origem, começa pela parada mais afastada do centro: um extremo do percurso.
     *
     * @param  list<array{lat: float, lng: float}>  $nodes
     */
    private function farthestFromCentroid(array $nodes): int
    {
        $centroid = [
            'lat' => array_sum(array_column($nodes, 'lat')) / count($nodes),
            'lng' => array_sum(array_column($nodes, 'lng')) / count($nodes),
        ];

        $best = 0;
        $bestDistance = -1.0;
        foreach ($nodes as $index => $node) {
            $distance = self::haversineMeters($centroid, $node);
            if ($distance > $bestDistance) {
                $bestDistance = $distance;
                $best = $index;
            }
        }

        return $best;
    }

    /**
     * @param  list<list<float>>  $matrix
     * @return list<int>
     */
    private function nearestNeighbor(array $matrix, int $start): array
    {
        $unvisited = array_fill_keys(array_keys($matrix), true);
        unset($unvisited[$start]);

        $path = [$start];
        $current = $start;

        while ($unvisited !== []) {
            $next = null;
            $nextDistance = INF;
            foreach (array_keys($unvisited) as $candidate) {
                if ($matrix[$current][$candidate] < $nextDistance) {
                    $nextDistance = $matrix[$current][$candidate];
                    $next = $candidate;
                }
            }

            $path[] = $next;
            unset($unvisited[$next]);
            $current = $next;
        }

        return $path;
    }

    /**
     * Inverte trechos enquanto houver ganho. O primeiro nó (origem ou ponto inicial) fica fixo.
     *
     * @param  list<list<float>>  $matrix
     * @param  list<int>  $path
     * @return list<int>
     */
    private function twoOpt(array $matrix, array $path, bool $closed): array
    {
        $count = count($path);
        if ($count < 4) {
            return $path;
        }

        $improved = true;
        while ($improved) {
            $improved = false;

            for ($i = 1; $i < $count - 1; $i++) {
                for ($k = $i + 1; $k < $count; $k++) {
                    $before = $path[$i - 1];
                    $first = $path[$i];
                    $last = $path[$k];
                    $after = $k + 1 < $count ? $path[$k + 1] : ($closed ? $path[0] : null);

                    $currentCost = $matrix[$before][$first] + ($after !== null ? $matrix[$last][$after] : 0.0);
                    $swappedCost = $matrix[$before][$last] + ($after !== null ? $matrix[$first][$after] : 0.0);

                    if ($swappedCost + 0.01 < $currentCost) {
                        array_splice($path, $i, $k - $i + 1, array_reverse(array_slice($path, $i, $k - $i + 1)));
                        $improved = true;
                    }
                }
            }
        }

        return $path;
    }

    private function roadMeters(float $straightLineMeters): int
    {
        return (int) round($straightLineMeters * self::ROAD_FACTOR);
    }
}
