<?php

namespace App\Services\Routing;

/**
 * Resultado do planejamento: ordem das paradas e o trecho que chega em cada uma.
 */
final class RoutePlan
{
    public const PROVIDER_GEOAPIFY = 'geoapify';

    /** Ordem e distâncias calculadas aqui, em linha reta com fator de estrada. */
    public const PROVIDER_LOCAL = 'local';

    /** Ordem decidida pelo Route Planner do Geoapify. */
    public const ORDER_PLANNER = 'planner';

    /** Ordem calculada aqui (vizinho mais próximo + 2-opt), sem custo. */
    public const ORDER_LOCAL = 'local';

    /** Ordem da rota base (ou da própria rota), com as paradas novas encaixadas aqui, sem custo. */
    public const ORDER_BASE = 'base';

    /** Sem crédito no dia para o Route Planner: ordem calculada aqui, trechos traçados pelas ruas. */
    public const NOTE_ORDER_DAILY_LIMIT = 'order_daily_limit';

    /** Sem crédito no dia nem para os trechos: rota inteira em linha reta. */
    public const NOTE_DAILY_LIMIT = 'daily_limit';

    /** O serviço de rotas falhou (fora do ar, recusou ou sem conexão). */
    public const NOTE_SERVICE_FAILED = 'service_failed';

    /**
     * @param  list<int|string>  $order  Ids das paradas na ordem de visita
     * @param  list<RouteLeg>  $legs  Um trecho por parada, na mesma ordem
     */
    public function __construct(
        public readonly array $order,
        public readonly array $legs,
        public readonly ?RouteLeg $returnLeg,
        public readonly string $provider,
        public readonly string $orderSource = self::ORDER_LOCAL,
        public readonly ?string $note = null,
    ) {}

    public function totalDistanceM(): int
    {
        return array_sum(array_map(fn (RouteLeg $leg): int => $leg->distanceM, $this->legs)) + ($this->returnLeg?->distanceM ?? 0);
    }

    public function totalDurationS(): ?int
    {
        $legs = [...$this->legs, ...($this->returnLeg === null ? [] : [$this->returnLeg])];

        if ($legs === [] || collect($legs)->contains(fn (RouteLeg $leg): bool => $leg->durationS === null)) {
            return null;
        }

        return array_sum(array_map(fn (RouteLeg $leg): int => (int) $leg->durationS, $legs));
    }
}
