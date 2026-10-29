<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\StoreDriverLocationsRequest;
use App\Models\DeliveryRoute;
use App\Services\Deliveries\DriverRouteService;
use Illuminate\Http\JsonResponse;

/**
 * Posições enviadas pelo app a cada 10 minutos e as guardadas sem sinal; uso interno da empresa.
 */
class DriverLocationController extends Controller
{
    public function __construct(private readonly DriverRouteService $routes) {}

    public function store(StoreDriverLocationsRequest $request, DeliveryRoute $deliveryRoute): JsonResponse
    {
        $stored = $this->routes->recordLocations($deliveryRoute, $request->user()->driver, $request->positions());

        return response()->json(['stored' => $stored], 201);
    }
}
