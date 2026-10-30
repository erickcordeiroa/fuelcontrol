<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryFailureReason;
use App\Enums\DeliveryRouteStatus;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\RouteStop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Execução da rota pelo motorista no app (spec 004): iniciar, dar baixa na ordem, finalizar e gravar posições.
 */
class DriverRouteService
{
    /** Posição pior que isso é gravada, mas não confirma o cliente. */
    public const PRECISE_ACCURACY_M = 200;

    /**
     * Rota do dia do motorista: a que está em andamento, senão a planejada, senão a concluída.
     */
    public function routeOfDay(Driver $driver, ?Carbon $day = null): ?DeliveryRoute
    {
        return DeliveryRoute::query()
            ->where('driver_id', $driver->id)
            ->whereDate('scheduled_for', ($day ?? now())->toDateString())
            ->with(['vehicle', 'stops.delivery.customer'])
            ->get()
            ->sortBy(fn (DeliveryRoute $route): int => match ($route->status) {
                DeliveryRouteStatus::InProgress => 0,
                DeliveryRouteStatus::Planned => 1,
                DeliveryRouteStatus::Completed => 2,
            })
            ->first();
    }

    /**
     * Próxima parada da fila: a de menor sequência ainda sem baixa.
     */
    public function nextStop(DeliveryRoute $route): ?RouteStop
    {
        return $route->stops->first(fn (RouteStop $stop): bool => ! $stop->delivery->status->isFinished());
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy_m?: int|null}|null  $position
     */
    public function start(DeliveryRoute $route, Driver $driver, ?array $position = null): DeliveryRoute
    {
        DB::transaction(function () use ($route, $driver, $position): void {
            $route = DeliveryRoute::query()->lockForUpdate()->findOrFail($route->id);

            if ($route->status === DeliveryRouteStatus::InProgress) {
                return;
            }

            if ($route->status !== DeliveryRouteStatus::Planned) {
                throw new DriverRouteException(__('Esta rota já foi concluída.'));
            }

            if ($route->stops()->doesntExist()) {
                throw new DriverRouteException(__('Esta rota não tem paradas.'));
            }

            $route->update(['status' => DeliveryRouteStatus::InProgress, 'started_at' => now()]);

            $route->stops()->with('delivery')->get()
                ->filter(fn (RouteStop $stop): bool => $stop->delivery->status === DeliveryStatus::Routed)
                ->each(fn (RouteStop $stop) => $stop->delivery->update(['status' => DeliveryStatus::InTransit]));

            $this->storePosition($route, $driver, $position);
        });

        return $route->refresh();
    }

    /**
     * Baixa na próxima parada. A mesma chave enviada de novo (reenvio sem sinal) não muda nada.
     *
     * @param  array{status: DeliveryStatus, client_uuid: string, reason?: DeliveryFailureReason|null, note?: string|null, position?: array{latitude: float, longitude: float, accuracy_m?: int|null}|null, completed_at?: Carbon|null}  $checkout
     */
    public function complete(RouteStop $stop, Driver $driver, array $checkout): Delivery
    {
        $repeated = Delivery::query()->where('client_uuid', $checkout['client_uuid'])->first();

        if ($repeated !== null) {
            if ($repeated->id !== $stop->delivery_id) {
                throw new DriverRouteException(__('Esta chave de envio já foi usada em outra parada.'));
            }

            return $repeated;
        }

        return DB::transaction(function () use ($stop, $driver, $checkout): Delivery {
            $route = DeliveryRoute::query()->lockForUpdate()->findOrFail($stop->delivery_route_id);

            if ($route->status !== DeliveryRouteStatus::InProgress) {
                throw new DriverRouteException(__('Inicie a rota para dar baixa nas entregas.'));
            }

            $next = $this->nextStop($route->load('stops.delivery'));
            $delivery = $stop->delivery()->firstOrFail();

            if ($delivery->status->isFinished()) {
                throw new DriverRouteException(__('Esta parada já teve baixa.'));
            }

            if ($next === null || $next->id !== $stop->id) {
                throw new DriverRouteException(__('Siga a ordem da rota: dê baixa na parada :sequence antes desta.', ['sequence' => $next?->sequence]));
            }

            $completedAt = $checkout['completed_at'] ?? now();
            $position = $checkout['position'] ?? null;
            $delivered = $checkout['status'] === DeliveryStatus::Delivered;
            $reason = $delivered ? null : $checkout['reason'];
            $note = $delivered ? null : ($checkout['note'] ?? null);

            $delivery->update([
                'status' => $checkout['status'],
                'delivered_at' => $delivered ? $completedAt : null,
                'failure_reason' => $reason === null ? null : mb_substr($reason === DeliveryFailureReason::Other && filled($note) ? $note : $reason->label(), 0, 255),
                'failure_reason_code' => $reason,
                'failure_note' => $note,
                'completed_by_driver_id' => $driver->id,
                'completed_latitude' => $position['latitude'] ?? null,
                'completed_longitude' => $position['longitude'] ?? null,
                'completed_accuracy_m' => $position['accuracy_m'] ?? null,
                'client_uuid' => $checkout['client_uuid'],
            ]);

            $stop->update(['arrived_at' => $completedAt]);

            if ($delivered && $this->isPrecise($position)) {
                $customer = $delivery->customer;

                if ($customer->confirmed_latitude === null || $customer->confirmed_longitude === null) {
                    $customer->confirmLocation($position['latitude'], $position['longitude']);
                }
            }

            $this->storePosition($route, $driver, $position, $completedAt);

            return $delivery;
        });
    }

    public function finish(DeliveryRoute $route): DeliveryRoute
    {
        DB::transaction(function () use ($route): void {
            $route = DeliveryRoute::query()->lockForUpdate()->findOrFail($route->id);

            if ($route->status === DeliveryRouteStatus::Completed) {
                return;
            }

            if ($route->status !== DeliveryRouteStatus::InProgress) {
                throw new DriverRouteException(__('Inicie a rota antes de finalizar.'));
            }

            $open = $route->stops()->whereHas('delivery', fn ($query) => $query->whereNotIn('status', [DeliveryStatus::Delivered, DeliveryStatus::Failed]))->count();

            if ($open > 0) {
                throw new DriverRouteException(trans_choice('{1} Falta dar baixa em 1 parada.|[2,*] Falta dar baixa em :count paradas.', $open, ['count' => $open]));
            }

            $route->update(['status' => DeliveryRouteStatus::Completed, 'finished_at' => now()]);
        });

        return $route->refresh();
    }

    /**
     * Grava o lote de posições enviado pelo app (inclusive as guardadas sem sinal), ignorando repetidas.
     *
     * @param  list<array{latitude: float, longitude: float, accuracy_m?: int|null, speed_kmh?: float|null, recorded_at: Carbon}>  $positions
     */
    public function recordLocations(DeliveryRoute $route, Driver $driver, array $positions): int
    {
        if ($route->status !== DeliveryRouteStatus::InProgress) {
            throw new DriverRouteException(__('A localização só é registrada com a rota em andamento.'));
        }

        $stored = 0;

        foreach ($positions as $position) {
            $exists = DriverLocation::query()
                ->where('delivery_route_id', $route->id)
                ->where('recorded_at', $position['recorded_at'])
                ->exists();

            if (! $exists) {
                $this->storePosition($route, $driver, $position, $position['recorded_at']);
                $stored++;
            }
        }

        return $stored;
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy_m?: int|null, speed_kmh?: float|null}|null  $position
     */
    private function storePosition(DeliveryRoute $route, Driver $driver, ?array $position, ?Carbon $recordedAt = null): void
    {
        if ($position === null) {
            return;
        }

        DriverLocation::query()->create([
            'user_id' => $route->user_id,
            'delivery_route_id' => $route->id,
            'driver_id' => $driver->id,
            'latitude' => round($position['latitude'], 7),
            'longitude' => round($position['longitude'], 7),
            'accuracy_m' => $position['accuracy_m'] ?? null,
            'speed_kmh' => $position['speed_kmh'] ?? null,
            'recorded_at' => $recordedAt ?? now(),
        ]);
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy_m?: int|null}|null  $position
     */
    private function isPrecise(?array $position): bool
    {
        return $position !== null
            && isset($position['accuracy_m'])
            && $position['accuracy_m'] <= self::PRECISE_ACCURACY_M;
    }
}
