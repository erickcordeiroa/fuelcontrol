<?php

namespace App\Services\Routing;

use App\Enums\DeliveryStatus;
use App\Enums\GeocodeStatus;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use Illuminate\Support\Collection;

/**
 * Converte pedidos e rotas no formato consumido pelo componente de mapa (resources/js/route-map.js).
 *
 * @phpstan-type MapPoint array{id: string, customerId: int, lat: float, lng: float, label: string, title: string, subtitle: string, color: string, draggable: bool}
 * @phpstan-type MapPayload array{fitKey: string, origin: array{lat: float, lng: float}|null, points: list<MapPoint>, path: list<array{0: float, 1: float}>, driver: array{lat: float, lng: float, label: string}|null}
 */
class RouteMapPresenter
{
    private const REVIEW_COLOR = '#F97316';

    private const FAILED_COLOR = '#dc2626';

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
            'path' => [],
            'driver' => null,
        ];
    }

    /**
     * Mapa de acompanhamento: paradas numeradas na ordem, cor pelo status, e a última posição do motorista.
     *
     * @return MapPayload
     */
    public function forRoute(DeliveryRoute $route): array
    {
        $route->loadMissing(['stops.delivery.customer', 'latestLocation', 'driver']);

        $located = $route->stops->filter(fn (RouteStop $stop): bool => $stop->delivery->customer->hasCoordinates());

        $points = $located->map(function (RouteStop $stop): array {
            $delivery = $stop->delivery;
            $coordinates = $delivery->customer->coordinates();

            return [
                'id' => 's-'.$stop->id,
                'customerId' => $delivery->customer_id,
                'lat' => $coordinates['lat'],
                'lng' => $coordinates['lng'],
                'label' => (string) $stop->sequence,
                'title' => $stop->sequence.'. '.$delivery->customer->name,
                'subtitle' => $delivery->customer->fullAddress().' · '.$delivery->status->label(),
                'color' => $delivery->status->color(),
                'draggable' => false,
            ];
        })->values()->all();

        $path = $located->map(fn (RouteStop $stop): array => array_values($stop->delivery->customer->coordinates()))->values()->all();
        $origin = $route->origin();
        if ($origin !== null) {
            array_unshift($path, [$origin['lat'], $origin['lng']]);
        }

        $location = $route->latestLocation;

        return [
            'fitKey' => 'route-'.$route->id,
            'origin' => $origin,
            'points' => $points,
            'path' => $path,
            'driver' => $location === null ? null : [
                'lat' => $location->latitude,
                'lng' => $location->longitude,
                'label' => $route->driver?->name ?? __('Motorista'),
            ],
        ];
    }
}
