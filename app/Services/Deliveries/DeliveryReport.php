<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryRouteStatus;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use App\Models\Trip;
use App\Services\MetricsService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Números das entregas no período, a partir das rotas criadas, cruzados com o gasto do Diário de Bordo
 * (combustível + despesas das viagens do mesmo motorista e dia): por motorista, por dia e motivos de não entrega.
 *
 * @phpstan-type Totals array{routes: int, stops: int, delivered: int, failed: int, open: int, amountDelivered: float, amountTotal: float, plannedKm: float, successRate: float|null}
 * @phpstan-type Costs array{cost: float, realKm: int, costPerDelivery: float|null, costShare: float|null}
 * @phpstan-type DriverRow array{driver: string, avgRouteMinutes: int|null, ...Totals, ...Costs}
 * @phpstan-type DayRow array{date: string, ...Totals, ...Costs}
 * @phpstan-type Report array{summary: array{...Totals, ...Costs}, unrouted: int, drivers: list<DriverRow>, days: list<DayRow>, reasons: list<array{label: string, count: int, percent: float}>}
 */
class DeliveryReport
{
    public function __construct(private readonly MetricsService $metrics) {}

    /**
     * @return Report
     */
    public function build(string $startDate, string $endDate, ?int $driverId = null): array
    {
        $routes = DeliveryRoute::query()
            ->whereDate('scheduled_for', '>=', $startDate)
            ->whereDate('scheduled_for', '<=', $endDate)
            ->when($driverId !== null, fn ($query) => $query->where('driver_id', $driverId))
            ->with(['driver:id,name', 'stops.delivery:id,status,amount,failure_reason,failure_reason_code'])
            ->orderBy('scheduled_for')
            ->get();

        $trips = $this->metrics->tripsQuery($startDate, $endDate, null, $driverId)
            ->with(['fuel', 'expenses', 'driver:id,name'])
            ->get();

        $unrouted = $driverId !== null ? 0 : Delivery::query()
            ->whereDate('scheduled_for', '>=', $startDate)
            ->whereDate('scheduled_for', '<=', $endDate)
            ->whereDoesntHave('routeStop')
            ->count();

        $routesByDriver = $routes->groupBy(fn (DeliveryRoute $route): string => (string) $route->driver_id);
        $tripsByDriver = $trips->groupBy(fn (Trip $trip): string => (string) $trip->driver_id);
        $driverNames = $routes->pluck('driver.name', 'driver_id')->union($trips->pluck('driver.name', 'driver_id'));

        $routesByDay = $routes->groupBy(fn (DeliveryRoute $route): string => $route->scheduled_for->toDateString());
        $tripsByDay = $trips->groupBy(fn (Trip $trip): string => $trip->date->toDateString());

        return [
            'summary' => $this->withCosts($this->totals($routes), $trips),
            'unrouted' => $unrouted,
            'drivers' => $routesByDriver->keys()->merge($tripsByDriver->keys())->unique()
                ->map(fn (string $key): array => [
                    'driver' => $key === '' ? __('Sem motorista') : ($driverNames[(int) $key] ?? __('Motorista removido')),
                    ...$this->withCosts($this->totals($routesByDriver[$key] ?? new Collection), $tripsByDriver[$key] ?? new Collection),
                    'avgRouteMinutes' => $this->averageRouteMinutes($routesByDriver[$key] ?? new Collection),
                ])
                ->sortByDesc('delivered')
                ->values()
                ->all(),
            'days' => $routesByDay->keys()->merge($tripsByDay->keys())->unique()->sort()
                ->map(fn (string $date): array => ['date' => $date, ...$this->withCosts($this->totals($routesByDay[$date] ?? new Collection), $tripsByDay[$date] ?? new Collection)])
                ->values()
                ->all(),
            'reasons' => $this->failureReasons($routes),
        ];
    }

    /**
     * Gasto das viagens (combustível + despesas) ao lado das entregas: custo por entrega e quanto do valor entregue
     * foi gasto para entregar.
     *
     * @param  Totals  $totals
     * @param  Collection<int, Trip>  $trips
     * @return array{...Totals, ...Costs}
     */
    private function withCosts(array $totals, Collection $trips): array
    {
        $cost = round((float) $trips->sum(fn (Trip $trip): float => $trip->operationalCost()), 2);

        return [
            ...$totals,
            'cost' => $cost,
            'realKm' => (int) $trips->sum('km_total'),
            'costPerDelivery' => $totals['delivered'] > 0 && $cost > 0 ? round($cost / $totals['delivered'], 2) : null,
            'costShare' => $totals['amountDelivered'] > 0 && $cost > 0 ? round($cost / $totals['amountDelivered'] * 100, 1) : null,
        ];
    }

    /**
     * @param  Collection<int, DeliveryRoute>  $routes
     * @return Totals
     */
    private function totals(Collection $routes): array
    {
        $deliveries = $routes->flatMap(fn (DeliveryRoute $route) => $route->stops->map(fn (RouteStop $stop): Delivery => $stop->delivery));
        $delivered = $deliveries->filter(fn (Delivery $delivery): bool => $delivery->status === DeliveryStatus::Delivered);
        $failed = $deliveries->filter(fn (Delivery $delivery): bool => $delivery->status === DeliveryStatus::Failed)->count();
        $finished = $delivered->count() + $failed;

        return [
            'routes' => $routes->count(),
            'stops' => $deliveries->count(),
            'delivered' => $delivered->count(),
            'failed' => $failed,
            'open' => $deliveries->count() - $finished,
            'amountDelivered' => round((float) $delivered->sum('amount'), 2),
            'amountTotal' => round((float) $deliveries->sum('amount'), 2),
            'plannedKm' => round($routes->sum('total_distance_m') / 1000, 1),
            'successRate' => $finished > 0 ? round($delivered->count() / $finished * 100, 1) : null,
        ];
    }

    /**
     * Tempo médio entre iniciar e encerrar a rota no app, só das rotas concluídas.
     *
     * @param  Collection<int, DeliveryRoute>  $routes
     */
    private function averageRouteMinutes(Collection $routes): ?int
    {
        $minutes = $routes
            ->filter(fn (DeliveryRoute $route): bool => $route->status === DeliveryRouteStatus::Completed && $route->started_at !== null && $route->finished_at !== null)
            ->map(fn (DeliveryRoute $route): float => $route->started_at->diffInMinutes($route->finished_at));

        return $minutes->isEmpty() ? null : (int) round($minutes->avg());
    }

    /**
     * Motivo escolhido no app; pedidos antigos ou do seeder têm só o texto livre.
     *
     * @param  Collection<int, DeliveryRoute>  $routes
     * @return list<array{label: string, count: int, percent: float}>
     */
    private function failureReasons(Collection $routes): array
    {
        $failed = $routes
            ->flatMap(fn (DeliveryRoute $route) => $route->stops->map(fn (RouteStop $stop): Delivery => $stop->delivery))
            ->filter(fn (Delivery $delivery): bool => $delivery->status === DeliveryStatus::Failed);

        return $failed
            ->countBy(fn (Delivery $delivery): string => $delivery->failure_reason_code?->label() ?? ($delivery->failure_reason ?: __('Sem motivo informado')))
            ->sortDesc()
            ->map(fn (int $count, string $label): array => ['label' => $label, 'count' => $count, 'percent' => round($count / $failed->count() * 100, 1)])
            ->values()
            ->all();
    }
}
