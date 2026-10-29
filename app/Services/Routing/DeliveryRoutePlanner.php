<?php

namespace App\Services\Routing;

use App\Enums\DeliveryRouteStatus;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeliveryRoutePlanner
{
    public function __construct(private readonly RouteOptimizer $optimizer) {}

    /**
     * Cria a rota com as paradas na ordem otimizada e marca os pedidos como roteirizados.
     *
     * @param  array{name: string, scheduled_for: string, driver_id?: int|null, vehicle_id?: int|null, user_id?: int|null, origin_latitude?: float|null, origin_longitude?: float|null}  $attributes
     * @param  Collection<int, Delivery>  $deliveries
     */
    public function create(array $attributes, Collection $deliveries): DeliveryRoute
    {
        if ($deliveries->isEmpty()) {
            throw new InvalidArgumentException(__('Selecione ao menos um pedido.'));
        }

        $deliveries = EloquentCollection::make($deliveries->values()->all())->loadMissing('customer');

        $withoutLocation = $deliveries->filter(fn (Delivery $delivery): bool => ! $delivery->customer->hasCoordinates());
        if ($withoutLocation->isNotEmpty()) {
            throw new InvalidArgumentException(__('Há :count pedido(s) sem localização. Revise os endereços antes de criar a rota.', [
                'count' => $withoutLocation->count(),
            ]));
        }

        $depot = DeliveryRoute::defaultOrigin();

        return DB::transaction(function () use ($attributes, $deliveries, $depot): DeliveryRoute {
            $route = DeliveryRoute::query()->create([
                ...$attributes,
                'origin_latitude' => $attributes['origin_latitude'] ?? $depot['lat'] ?? null,
                'origin_longitude' => $attributes['origin_longitude'] ?? $depot['lng'] ?? null,
                'status' => DeliveryRouteStatus::Planned,
            ]);

            $this->sequence($route, $deliveries);

            return $route;
        });
    }

    /**
     * Recalcula a ordem das paradas ainda não concluídas, mantendo as já finalizadas no início.
     */
    public function reoptimize(DeliveryRoute $route): DeliveryRoute
    {
        return DB::transaction(function () use ($route): DeliveryRoute {
            $route->load('stops.delivery.customer');

            $deliveries = $route->stops->map(fn ($stop) => $stop->delivery);
            $route->stops()->delete();
            $this->sequence($route, $deliveries);

            return $route->refresh();
        });
    }

    /**
     * @param  Collection<int, Delivery>  $deliveries
     */
    private function sequence(DeliveryRoute $route, Collection $deliveries): void
    {
        [$finished, $open] = $deliveries->partition(fn (Delivery $delivery): bool => $delivery->status->isFinished());

        $points = $open
            ->mapWithKeys(fn (Delivery $delivery): array => [$delivery->id => $delivery->customer->coordinates()])
            ->all();

        $result = $this->optimizer->optimize($points, $route->origin());

        $sequence = 0;
        foreach ($finished->values() as $delivery) {
            $route->stops()->create(['delivery_id' => $delivery->id, 'sequence' => ++$sequence]);
        }

        $byId = $open->keyBy('id');
        foreach ($result['order'] as $index => $deliveryId) {
            $route->stops()->create([
                'delivery_id' => $deliveryId,
                'sequence' => ++$sequence,
                'distance_from_previous_m' => $result['legs_m'][$index],
            ]);

            $delivery = $byId[$deliveryId];
            if ($delivery->status === DeliveryStatus::Pending) {
                $delivery->update(['status' => DeliveryStatus::Routed]);
            }
        }

        $route->forceFill([
            'total_distance_m' => $result['total_m'],
            'optimized_at' => now(),
        ])->save();
    }
}
