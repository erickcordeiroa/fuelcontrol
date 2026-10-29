<?php

namespace Tests\Unit;

use App\Services\Routing\RouteOptimizer;
use PHPUnit\Framework\TestCase;

class RouteOptimizerTest extends TestCase
{
    public function test_empty_points_return_empty_route(): void
    {
        $result = (new RouteOptimizer)->optimize([]);

        $this->assertSame(['order' => [], 'legs_m' => [], 'total_m' => 0], $result);
    }

    public function test_single_point_without_origin(): void
    {
        $result = (new RouteOptimizer)->optimize([42 => ['lat' => -16.7, 'lng' => -49.2]]);

        $this->assertSame([42], $result['order']);
        $this->assertSame([0], $result['legs_m']);
        $this->assertSame(0, $result['total_m']);
    }

    public function test_haversine_one_degree_of_latitude_is_about_111_km(): void
    {
        $meters = RouteOptimizer::haversineMeters(['lat' => 0.0, 'lng' => 0.0], ['lat' => 1.0, 'lng' => 0.0]);

        $this->assertEqualsWithDelta(111_195, $meters, 50);
    }

    public function test_points_on_a_line_are_visited_in_order_from_the_origin(): void
    {
        $points = [
            'c' => ['lat' => 0.0, 'lng' => 0.03],
            'a' => ['lat' => 0.0, 'lng' => 0.01],
            'd' => ['lat' => 0.0, 'lng' => 0.04],
            'b' => ['lat' => 0.0, 'lng' => 0.02],
        ];

        $result = (new RouteOptimizer)->optimize($points, ['lat' => 0.0, 'lng' => 0.0], returnToOrigin: false);

        $this->assertSame(['a', 'b', 'c', 'd'], $result['order']);
    }

    public function test_without_origin_the_route_starts_at_an_end_of_the_line(): void
    {
        $points = [
            1 => ['lat' => 0.0, 'lng' => 0.02],
            2 => ['lat' => 0.0, 'lng' => 0.04],
            3 => ['lat' => 0.0, 'lng' => 0.0],
            4 => ['lat' => 0.0, 'lng' => 0.03],
            5 => ['lat' => 0.0, 'lng' => 0.01],
        ];

        $order = (new RouteOptimizer)->optimize($points)['order'];

        $this->assertContains($order, [[3, 5, 1, 4, 2], [2, 4, 1, 5, 3]]);
    }

    public function test_optimized_route_has_no_crossing_edges(): void
    {
        mt_srand(11);
        $points = [];
        for ($i = 0; $i < 30; $i++) {
            $points[$i] = ['lat' => mt_rand(0, 1000) / 100000, 'lng' => mt_rand(0, 1000) / 100000];
        }
        $origin = ['lat' => 0.0, 'lng' => 0.0];

        $order = (new RouteOptimizer)->optimize($points, $origin)['order'];

        $path = [$origin, ...array_map(fn (int $key): array => $points[$key], $order), $origin];
        $edges = count($path) - 1;

        for ($i = 0; $i < $edges; $i++) {
            for ($j = $i + 2; $j < $edges; $j++) {
                if ($i === 0 && $j === $edges - 1) {
                    continue;
                }

                $this->assertFalse(
                    $this->segmentsCross($path[$i], $path[$i + 1], $path[$j], $path[$j + 1]),
                    "Arestas {$i} e {$j} se cruzam.",
                );
            }
        }
    }

    public function test_total_includes_return_to_origin_and_matches_the_legs(): void
    {
        $points = [
            1 => ['lat' => -16.70, 'lng' => -49.25],
            2 => ['lat' => -16.71, 'lng' => -49.26],
            3 => ['lat' => -16.69, 'lng' => -49.27],
        ];
        $origin = ['lat' => -16.705, 'lng' => -49.29];
        $optimizer = new RouteOptimizer;

        $open = $optimizer->optimize($points, $origin, returnToOrigin: false);
        $closed = $optimizer->optimize($points, $origin);

        $this->assertSame(array_sum($open['legs_m']), $open['total_m']);
        $this->assertGreaterThan(array_sum($closed['legs_m']), $closed['total_m']);
        $this->assertGreaterThan(0, $open['legs_m'][0]);
    }

    public function test_optimized_route_is_never_longer_than_the_input_order(): void
    {
        mt_srand(7);
        $points = [];
        for ($i = 0; $i < 60; $i++) {
            $points[$i] = ['lat' => -16.75 + mt_rand(0, 1000) / 10000, 'lng' => -49.33 + mt_rand(0, 1000) / 10000];
        }
        $origin = ['lat' => -16.70, 'lng' => -49.29];

        $optimizer = new RouteOptimizer;
        $result = $optimizer->optimize($points, $origin);

        $inputOrderTotal = 0.0;
        $previous = $origin;
        foreach ($points as $point) {
            $inputOrderTotal += RouteOptimizer::haversineMeters($previous, $point);
            $previous = $point;
        }
        $inputOrderTotal = ($inputOrderTotal + RouteOptimizer::haversineMeters($previous, $origin)) * RouteOptimizer::ROAD_FACTOR;

        $this->assertCount(60, $result['order']);
        $this->assertEqualsCanonicalizing(array_keys($points), $result['order']);
        $this->assertLessThan($inputOrderTotal * 0.5, $result['total_m']);
    }

    /**
     * Interseção estrita de segmentos em coordenadas planas (área pequena perto do equador).
     *
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     * @param  array{lat: float, lng: float}  $c
     * @param  array{lat: float, lng: float}  $d
     */
    private function segmentsCross(array $a, array $b, array $c, array $d): bool
    {
        $orientation = fn (array $p, array $q, array $r): float => ($q['lng'] - $p['lng']) * ($r['lat'] - $p['lat']) - ($q['lat'] - $p['lat']) * ($r['lng'] - $p['lng']);

        $d1 = $orientation($c, $d, $a);
        $d2 = $orientation($c, $d, $b);
        $d3 = $orientation($a, $b, $c);
        $d4 = $orientation($a, $b, $d);

        return $d1 * $d2 < 0 && $d3 * $d4 < 0;
    }
}
