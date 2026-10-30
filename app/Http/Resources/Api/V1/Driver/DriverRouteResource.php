<?php

namespace App\Http\Resources\Api\V1\Driver;

use App\Enums\DeliveryRouteStatus;
use App\Enums\DeliveryStatus;
use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use App\Services\Routing\RoutePlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rota do dia no app do motorista (spec 004): pendentes na ordem calculada e baixadas no fim da fila.
 *
 * @mixin DeliveryRoute
 */
class DriverRouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['vehicle', 'stops.delivery.customer']);

        [$finished, $open] = $this->stops->partition(fn (RouteStop $stop): bool => $stop->delivery->status->isFinished());
        $next = $open->first();
        $queue = $open->concat($finished->sortBy(fn (RouteStop $stop): int => $stop->arrived_at?->getTimestamp() ?? 0)->values());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'scheduled_for' => $this->scheduled_for->toDateString(),
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'vehicle' => $this->vehicle === null ? null : ['plate' => $this->vehicle->plate, 'model' => $this->vehicle->model],
            'total_distance_km' => $this->total_distance_m === null ? null : round($this->total_distance_m / 1000, 1),
            'total_duration_min' => $this->total_duration_s === null ? null : (int) ceil($this->total_duration_s / 60),
            'road_routing' => $this->routing_provider === RoutePlan::PROVIDER_GEOAPIFY,
            'origin' => $this->origin(),
            'return_path' => $this->return_path_polyline,
            'total_amount' => round($this->stops->sum(fn (RouteStop $stop): float => $stop->delivery->amount), 2),
            'progress' => [
                'total' => $this->stops->count(),
                'delivered' => $finished->filter(fn (RouteStop $stop): bool => $stop->delivery->status === DeliveryStatus::Delivered)->count(),
                'failed' => $finished->filter(fn (RouteStop $stop): bool => $stop->delivery->status === DeliveryStatus::Failed)->count(),
            ],
            'next_stop_id' => $next?->id,
            'can_finish' => $this->status === DeliveryRouteStatus::InProgress && $open->isEmpty(),
            'stops' => $queue->map(fn (RouteStop $stop): DriverStopResource => new DriverStopResource($stop, $stop->id === $next?->id))->values(),
        ];
    }
}
