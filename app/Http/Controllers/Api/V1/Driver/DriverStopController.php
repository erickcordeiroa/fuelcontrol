<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\DeliverStopRequest;
use App\Http\Requests\Api\V1\Driver\FailStopRequest;
use App\Http\Requests\Api\V1\Driver\StopDirectionsRequest;
use App\Http\Resources\Api\V1\Driver\DirectionsResource;
use App\Http\Resources\Api\V1\Driver\DriverRouteResource;
use App\Models\RouteStop;
use App\Services\Deliveries\DriverRouteException;
use App\Services\Deliveries\DriverRouteService;
use App\Services\Routing\RoutePlanningService;
use App\Services\Routing\RoutingException;

/**
 * Baixa das paradas; responde com a rota inteira para o app redesenhar a fila.
 */
class DriverStopController extends Controller
{
    public function __construct(private readonly DriverRouteService $routes) {}

    public function deliver(DeliverStopRequest $request, RouteStop $stop): DriverRouteResource
    {
        $this->routes->complete($stop, $request->user()->driver, [
            'status' => DeliveryStatus::Delivered,
            'client_uuid' => $request->string('client_uuid')->toString(),
            'position' => $request->position(),
            'completed_at' => $request->completedAt(),
        ]);

        return new DriverRouteResource($stop->deliveryRoute->refresh());
    }

    /**
     * Novo trajeto da posição atual até a parada: o motorista saiu do caminho traçado.
     */
    public function directions(StopDirectionsRequest $request, RouteStop $stop, RoutePlanningService $planning): DirectionsResource
    {
        if ($stop->delivery->status->isFinished()) {
            throw new DriverRouteException(__('Esta parada já teve baixa.'));
        }

        $destination = $stop->delivery->customer->coordinates();

        if ($destination === null) {
            throw new DriverRouteException(__('Esta parada não tem localização.'));
        }

        try {
            $leg = $planning->directions($request->from(), $destination, $stop->deliveryRoute->user_id);
        } catch (RoutingException $e) {
            throw new DriverRouteException($e->getMessage());
        }

        return new DirectionsResource($leg);
    }

    public function fail(FailStopRequest $request, RouteStop $stop): DriverRouteResource
    {
        $this->routes->complete($stop, $request->user()->driver, [
            'status' => DeliveryStatus::Failed,
            'client_uuid' => $request->string('client_uuid')->toString(),
            'reason' => $request->reason(),
            'note' => $request->filled('note') ? $request->string('note')->squish()->toString() : null,
            'position' => $request->position(),
            'completed_at' => $request->completedAt(),
        ]);

        return new DriverRouteResource($stop->deliveryRoute->refresh());
    }
}
