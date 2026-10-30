<?php

namespace App\Services\Routing;

/**
 * Um trecho da rota até a próxima parada (ou a volta ao centro de distribuição).
 */
final class RouteLeg
{
    /**
     * @param  list<array{0: float, 1: float}>  $path  Trajeto em pares [lat, lng]
     * @param  list<array{instruction: string, type: string|null, distance_m: int, time_s: int, from_index: int, to_index: int}>  $steps  Instruções de navegação
     */
    public function __construct(
        public readonly int $distanceM,
        public readonly ?int $durationS,
        public readonly array $path,
        public readonly array $steps = [],
    ) {}
}
