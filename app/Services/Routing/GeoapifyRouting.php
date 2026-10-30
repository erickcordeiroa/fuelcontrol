<?php

namespace App\Services\Routing;

use App\Models\GeocodingRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Rotas pelas ruas na conta da plataforma: Route Planner decide a ordem, Routing traça o caminho com
 * as instruções em português para o GPS do app. Cada chamada fica registrada com os créditos gastos.
 */
class GeoapifyRouting
{
    private const PLANNER_ENDPOINT = 'https://api.geoapify.com/v1/routeplanner';

    private const ROUTING_ENDPOINT = 'https://api.geoapify.com/v1/routing';

    /**
     * Route Planner: locais² até 9 locais, locais × 10 a partir de 10. Contam a saída, a chegada e cada parada.
     *
     * @see https://www.geoapify.com/pricing-details/
     */
    private const PLANNER_CREDIT_CAP = 10;

    public function __construct(private readonly ?string $apiKey) {}

    /**
     * Créditos de planejar e traçar uma rota com estas paradas (Route Planner + Routing).
     */
    public static function creditsFor(int $stops, bool $hasEnd): int
    {
        $waypoints = $stops + 1 + ($hasEnd ? 1 : 0);

        return ($stops > 1 ? self::plannerCredits($waypoints) : 0) + ($waypoints - 1);
    }

    public static function plannerCredits(int $locations): int
    {
        return $locations * min($locations, self::PLANNER_CREDIT_CAP);
    }

    /**
     * Caminho de um ponto a outro (o motorista saiu do trajeto ou começou de onde está).
     *
     * @param  array{lat: float, lng: float}  $from
     * @param  array{lat: float, lng: float}  $to
     */
    public function directions(array $from, array $to, ?int $tenantId): RouteLeg
    {
        return $this->legs([$from, $to], $tenantId)[0];
    }

    /**
     * Ordem de visita pelo Route Planner (a parte cara: cobra por local).
     *
     * @param  array{lat: float, lng: float}  $start
     * @param  array<int|string, array{lat: float, lng: float}>  $points
     * @param  array{lat: float, lng: float}|null  $end
     * @return list<int|string>
     *
     * @throws RoutingException
     */
    public function optimizeOrder(array $start, array $points, ?array $end, ?int $tenantId): array
    {
        $ids = array_keys($points);
        $agent = ['start_location' => [$start['lng'], $start['lat']]];
        if ($end !== null) {
            $agent['end_location'] = [$end['lng'], $end['lat']];
        }

        $locations = count($points) + 1 + ($end !== null ? 1 : 0);
        $response = $this->send(
            fn () => Http::connectTimeout(5)->timeout(30)->asJson()->post(self::PLANNER_ENDPOINT.'?apiKey='.urlencode((string) $this->apiKey), [
                'mode' => 'drive',
                'type' => 'balanced',
                'agents' => [$agent],
                'jobs' => array_map(fn (int $index): array => [
                    'id' => (string) $index,
                    'location' => [$points[$ids[$index]]['lng'], $points[$ids[$index]]['lat']],
                ], array_keys($ids)),
            ]),
            GeocodingRequest::LOOKUP_ROUTE_PLANNER,
            self::plannerCredits($locations),
            $tenantId,
        );

        $order = collect($response->json('features.0.properties.actions', []))
            ->filter(fn (array $action): bool => ($action['type'] ?? null) === 'job')
            ->sortBy('start_time')
            ->map(fn (array $action): int|string => $ids[(int) $action['job_id']])
            ->values()
            ->all();

        if (count($order) !== count($ids)) {
            throw new RoutingException(__('O serviço de rotas não conseguiu incluir todas as paradas.'));
        }

        return $order;
    }

    /**
     * Trajeto pelas ruas passando pelos pontos na ordem dada: um trecho por par de pontos (1 crédito cada).
     *
     * @param  list<array{lat: float, lng: float}>  $waypoints
     * @return list<RouteLeg>
     *
     * @throws RoutingException
     */
    public function legs(array $waypoints, ?int $tenantId): array
    {
        $response = $this->send(
            fn () => Http::connectTimeout(5)->timeout(30)->get(self::ROUTING_ENDPOINT, [
                'waypoints' => collect($waypoints)->map(fn (array $point): string => round($point['lat'], 6).','.round($point['lng'], 6))->implode('|'),
                'mode' => 'drive',
                'details' => 'instruction_details',
                'lang' => 'pt',
                'format' => 'json',
                'apiKey' => $this->apiKey,
            ]),
            GeocodingRequest::LOOKUP_ROUTING,
            count($waypoints) - 1,
            $tenantId,
        );

        $legs = $response->json('results.0.legs', []);
        $geometry = $response->json('results.0.geometry', []);

        if (count($legs) !== count($waypoints) - 1) {
            throw new RoutingException(__('O serviço de rotas devolveu um trajeto incompleto.'));
        }

        return array_map(fn (array $leg, int $index): RouteLeg => new RouteLeg(
            distanceM: (int) round($leg['distance'] ?? 0),
            durationS: (int) round($leg['time'] ?? 0),
            path: array_map(fn (array $point): array => [(float) $point['lat'], (float) $point['lon']], $geometry[$index] ?? []),
            steps: array_map(fn (array $step): array => [
                'instruction' => (string) ($step['instruction']['text'] ?? ''),
                'type' => $step['instruction']['type'] ?? null,
                'distance_m' => (int) round($step['distance'] ?? 0),
                'time_s' => (int) round($step['time'] ?? 0),
                'from_index' => (int) ($step['from_index'] ?? 0),
                'to_index' => (int) ($step['to_index'] ?? 0),
            ], $leg['steps'] ?? []),
        ), $legs, array_keys($legs));
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request, string $lookup, int $credits, ?int $tenantId): Response
    {
        if (blank($this->apiKey)) {
            throw new RoutingException(__('Serviço de rotas não configurado na plataforma.'));
        }

        try {
            $response = $request();
        } catch (ConnectionException $e) {
            report(new RoutingException(str_replace((string) $this->apiKey, '[redacted]', $e->getMessage())));

            throw new RoutingException(__('Sem conexão com o serviço de rotas.'));
        }

        GeocodingRequest::query()->withoutGlobalScopes()->create([
            'user_id' => $tenantId,
            'provider' => 'geoapify',
            'lookup' => $lookup,
            'provider_status' => $response->successful() ? 'OK' : 'HTTP_'.$response->status(),
            'billable' => $response->successful(),
            'credits' => $response->successful() ? $credits : 0,
        ]);

        if ($response->failed()) {
            throw new RoutingException(__('O serviço de rotas recusou o pedido (HTTP :status).', ['status' => $response->status()]));
        }

        return $response;
    }
}
