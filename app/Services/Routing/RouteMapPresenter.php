<?php

namespace App\Services\Routing;

use App\Enums\DeliveryRouteStatus;
use App\Enums\DeliveryStatus;
use App\Enums\GeocodeStatus;
use App\Livewire\Deliveries\RouteMonitor;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\DriverLocation;
use App\Models\RouteStop;
use App\Support\Polyline;
use Illuminate\Support\Collection;

/**
 * Converte pedidos e rotas no formato consumido pelo componente de mapa (resources/js/route-map.js).
 *
 * @phpstan-type MapPoint array{id: string, customerId: int, lat: float, lng: float, label: string, title: string, subtitle: string, color: string, draggable: bool}
 * @phpstan-type MapPath array{points: list<array{0: float, 1: float}>, color: string, dashed: bool}
 * @phpstan-type MapDriver array{lat: float, lng: float, label: string, subtitle: string, color: string, idle: bool, finished: bool}
 * @phpstan-type MapPayload array{fitKey: string, origin: array{lat: float, lng: float}|null, points: list<MapPoint>, paths: list<MapPath>, drivers: list<MapDriver>}
 */
class RouteMapPresenter
{
    /** Cores das rotas no mapa do dia: distintas entre si e das cores de status das paradas. */
    private const ROUTE_COLORS = ['#0061FE', '#7C3AED', '#0E7490', '#C2410C', '#BE185D', '#4D7C0F', '#1E1919'];

    private const REVIEW_COLOR = '#7A5A00';

    private const FAILED_COLOR = '#B42318';

    /**
     * Mapa de revisão: um pino por cliente do dia, arrastável para corrigir a posição.
     *
     * @param  Collection<int, Delivery>  $deliveries
     * @return MapPayload
     */
    public function forPlanning(Collection $deliveries, string $fitKey): array
    {
        $points = $deliveries
            ->filter(fn (Delivery $delivery): bool => $delivery->customer->hasCoordinates())
            ->unique('customer_id')
            ->map(function (Delivery $delivery): array {
                $customer = $delivery->customer;
                $coordinates = $customer->coordinates();
                $status = $customer->geocode_status;

                return [
                    'id' => 'c-'.$customer->id,
                    'customerId' => $customer->id,
                    'lat' => $coordinates['lat'],
                    'lng' => $coordinates['lng'],
                    'label' => $delivery->sector ? mb_substr($delivery->sector, 0, 1) : '•',
                    'title' => $customer->name,
                    'subtitle' => $customer->fullAddress().' · '.$status->label(),
                    'color' => match (true) {
                        $status === GeocodeStatus::Failed => self::FAILED_COLOR,
                        $status->needsReview() => self::REVIEW_COLOR,
                        $delivery->status === DeliveryStatus::Pending => DeliveryStatus::Pending->color(),
                        default => DeliveryStatus::Routed->color(),
                    },
                    'draggable' => true,
                ];
            })
            ->values()
            ->all();

        return [
            'fitKey' => $fitKey,
            'origin' => DeliveryRoute::defaultOrigin(),
            'points' => $points,
            'paths' => [],
            'drivers' => [],
        ];
    }

    /**
     * Mapa de acompanhamento de uma rota: paradas numeradas, trajeto, rastro e a última posição do motorista.
     *
     * @return MapPayload
     */
    public function forRoute(DeliveryRoute $route, int $colorIndex = 0): array
    {
        $route->loadMissing(['stops.delivery.customer', 'latestLocation', 'driver']);
        $color = self::routeColor($colorIndex);

        return [
            'fitKey' => 'route-'.$route->id,
            'origin' => $route->origin(),
            'points' => $this->stopPoints($route, withDriverName: false),
            'paths' => [
                ['points' => $this->plannedPath($route), 'color' => $color, 'dashed' => false],
                ['points' => $this->trail($route), 'color' => '#1E1919', 'dashed' => true],
            ],
            'drivers' => array_filter([$this->driverMarker($route, $color)]),
        ];
    }

    /**
     * Mapa da operação do dia: todas as rotas, cada uma com sua cor, e todos os motoristas com o nome à vista.
     *
     * @param  Collection<int, DeliveryRoute>  $routes
     * @return MapPayload
     */
    public function forOverview(Collection $routes, string $fitKey): array
    {
        $routes->loadMissing(['stops.delivery.customer', 'latestLocation', 'driver']);
        $routes = $routes->values();

        return [
            'fitKey' => $fitKey,
            'origin' => $routes->map(fn (DeliveryRoute $route): ?array => $route->origin())->filter()->first() ?? DeliveryRoute::defaultOrigin(),
            'points' => $routes->flatMap(fn (DeliveryRoute $route): array => $this->stopPoints($route, withDriverName: true))->all(),
            'paths' => $routes->map(fn (DeliveryRoute $route, int $index): array => [
                'points' => $this->plannedPath($route),
                'color' => self::routeColor($index),
                'dashed' => false,
            ])->all(),
            'drivers' => $routes->map(fn (DeliveryRoute $route, int $index): ?array => $this->driverMarker($route, self::routeColor($index)))->filter()->values()->all(),
        ];
    }

    /**
     * Cor da rota no mapa e no cartão do motorista; separada das cores de status das paradas.
     */
    public static function routeColor(int $index): string
    {
        return self::ROUTE_COLORS[$index % count(self::ROUTE_COLORS)];
    }

    /**
     * @return list<MapPoint>
     */
    private function stopPoints(DeliveryRoute $route, bool $withDriverName): array
    {
        return $route->stops
            ->filter(fn (RouteStop $stop): bool => $stop->delivery->customer->hasCoordinates())
            ->map(function (RouteStop $stop) use ($route, $withDriverName): array {
                $delivery = $stop->delivery;
                $coordinates = $delivery->customer->coordinates();
                $owner = $withDriverName ? ' · '.($route->driver?->name ?? $route->name) : '';

                return [
                    'id' => 's-'.$stop->id,
                    'customerId' => $delivery->customer_id,
                    'lat' => $coordinates['lat'],
                    'lng' => $coordinates['lng'],
                    'label' => (string) $stop->sequence,
                    'title' => $stop->sequence.'. '.$delivery->customer->name,
                    'subtitle' => $delivery->customer->fullAddress().' · '.$delivery->status->label().$owner,
                    'color' => $delivery->status->color(),
                    'draggable' => false,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    private function plannedPath(DeliveryRoute $route): array
    {
        $located = $route->stops->filter(fn (RouteStop $stop): bool => $stop->delivery->customer->hasCoordinates());

        return $this->roadPath($route) ?? $this->straightPath($located, $route->origin());
    }

    /**
     * Caminho feito pelo motorista hoje, pelas posições enviadas pelo app.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function trail(DeliveryRoute $route): array
    {
        return $route->locations()
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude'])
            ->map(fn (DriverLocation $location): array => [$location->latitude, $location->longitude])
            ->all();
    }

    /**
     * @return MapDriver|null
     */
    private function driverMarker(DeliveryRoute $route, string $color): ?array
    {
        $location = $route->latestLocation;

        if ($location === null) {
            return null;
        }

        $minutes = (int) $location->recorded_at->diffInMinutes(now());

        return [
            'lat' => $location->latitude,
            'lng' => $location->longitude,
            'label' => $route->driver?->name ?? __('Motorista'),
            'subtitle' => __(':route · posição das :time (há :min min)', ['route' => $route->name, 'time' => $location->recorded_at->format('H:i'), 'min' => $minutes]),
            'color' => $color,
            'idle' => $route->status === DeliveryRouteStatus::InProgress && $minutes >= RouteMonitor::IDLE_AFTER_MINUTES,
            'finished' => $route->status === DeliveryRouteStatus::Completed,
        ];
    }

    /**
     * Trajeto pelas ruas guardado no planejamento (Geoapify), parada a parada e a volta.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    private function roadPath(DeliveryRoute $route): ?array
    {
        if ($route->stops->isEmpty() || $route->stops->contains(fn (RouteStop $stop): bool => $stop->path_polyline === null && $stop->sequence > 1)) {
            return null;
        }

        $path = $route->stops->flatMap(fn (RouteStop $stop): array => Polyline::decode($stop->path_polyline))->all();
        $path = [...$path, ...Polyline::decode($route->return_path_polyline)];

        return count($path) > 1 ? $path : null;
    }

    /**
     * @param  Collection<int, RouteStop>  $located
     * @param  array{lat: float, lng: float}|null  $origin
     * @return list<array{0: float, 1: float}>
     */
    private function straightPath(Collection $located, ?array $origin): array
    {
        $path = $located->map(fn (RouteStop $stop): array => array_values($stop->delivery->customer->coordinates()))->values()->all();

        if ($origin !== null) {
            array_unshift($path, [$origin['lat'], $origin['lng']]);
        }

        return $path;
    }
}
