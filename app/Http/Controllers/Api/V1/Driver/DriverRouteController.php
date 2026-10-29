<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\StartRouteRequest;
use App\Http\Resources\Api\V1\Driver\DriverRouteResource;
use App\Models\DeliveryRoute;
use App\Services\Deliveries\DriverRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DriverRouteController extends Controller
{
    public function __construct(private readonly DriverRouteService $routes) {}

    public function today(Request $request): DriverRouteResource|JsonResponse
    {
        $route = $this->routes->routeOfDay($request->user()->driver);

        if ($route === null) {
            return response()->json(['data' => null, 'message' => __('Nenhuma rota para hoje.')]);
        }

        return new DriverRouteResource($route);
    }

    public function start(StartRouteRequest $request, DeliveryRoute $deliveryRoute): DriverRouteResource
    {
        return new DriverRouteResource($this->routes->start($deliveryRoute, $request->user()->driver, $request->position()));
    }

    public function finish(Request $request, DeliveryRoute $deliveryRoute): DriverRouteResource
    {
        Gate::authorize('drive', $deliveryRoute);

        return new DriverRouteResource($this->routes->finish($deliveryRoute));
    }
}
