<?php

namespace App\Livewire\Deliveries;

use App\Enums\DeliveryRouteStatus;
use App\Enums\DeliveryStatus;
use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use App\Services\Routing\RouteMapPresenter;
use App\Services\Routing\RouteOptimizer;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Acompanhamento de rotas')]
class RouteMonitor extends Component
{
    /**
     * Sem posição nova há mais que isso, com a rota em andamento, o motorista aparece como parado.
     */
    public const IDLE_AFTER_MINUTES = 10;

    /**
     * Intervalo da atualização automática da tela (o botão "Atualizar agora" busca na hora).
     */
    public const REFRESH_MINUTES = 10;

    /**
     * Velocidade média urbana usada no ETA quando não há velocidade recente do GPS.
     */
    private const DEFAULT_SPEED_KMH = 25.0;

    #[Url(as: 'data')]
    public string $date = '';

    #[Url(as: 'rota')]
    public ?int $routeId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', DeliveryRoute::class);

        if ($this->date === '' || ! Carbon::canBeCreatedFromFormat($this->date, 'Y-m-d')) {
            $this->date = now()->toDateString();
        }
    }

    public function updatedDate(): void
    {
        if (! Carbon::canBeCreatedFromFormat($this->date, 'Y-m-d')) {
            $this->date = now()->toDateString();
        }

        $this->routeId = null;
    }

    public function selectRoute(int $routeId): void
    {
        $this->routeId = $routeId;
    }

    /**
     * Volta para o mapa com todos os motoristas do dia.
     */
    public function showAll(): void
    {
        $this->routeId = null;
    }

    public function render(RouteMapPresenter $presenter): View
    {
        $routes = $this->routesOfDay();
        $colorIndex = $routes->pluck('id')->flip();

        /** Com uma rota só no dia, ela já é a visão geral. */
        $route = $this->routeId !== null ? $routes->firstWhere('id', $this->routeId) : ($routes->count() === 1 ? $routes->first() : null);

        if ($route !== null) {
            Gate::authorize('view', $route);
            $mapPayload = $presenter->forRoute($route, $colorIndex[$route->id]);
        } elseif ($routes->isNotEmpty()) {
            $mapPayload = $presenter->forOverview($routes, 'overview-'.$this->date.'-'.$routes->count());
        }

        if (isset($mapPayload)) {
            $this->dispatch('route-map-update', payload: $mapPayload);
        }

        return view('livewire.deliveries.route-monitor', [
            'routes' => $routes,
            'routeColors' => $routes->mapWithKeys(fn (DeliveryRoute $item): array => [$item->id => RouteMapPresenter::routeColor($colorIndex[$item->id])])->all(),
            'nextStops' => $routes->mapWithKeys(fn (DeliveryRoute $item): array => [$item->id => $this->nextStop($item)])->all(),
            'idleRouteIds' => $routes->filter(fn (DeliveryRoute $r): bool => $this->isIdle($r))->modelKeys(),
            'route' => $route,
            'summary' => $route !== null ? $this->summarize($route) : null,
            'overview' => $route === null && $routes->isNotEmpty() ? $this->overview($routes) : null,
            'mapPayload' => $mapPayload ?? null,
        ]);
    }

    /**
     * @return Collection<int, DeliveryRoute>
     */
    private function routesOfDay(): Collection
    {
        $countByStatus = fn (DeliveryStatus $status) => fn (Builder $query) => $query->whereHas(
            'delivery',
            fn (Builder $delivery) => $delivery->where('status', $status),
        );

        return DeliveryRoute::query()
            ->whereDate('scheduled_for', $this->date)
            ->with(['driver', 'vehicle', 'latestLocation', 'stops.delivery.customer'])
            ->withCount([
                'stops',
                'stops as delivered_count' => $countByStatus(DeliveryStatus::Delivered),
                'stops as failed_count' => $countByStatus(DeliveryStatus::Failed),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{total: int, delivered: int, failed: int, amountDelivered: float, amountTotal: float, kmRemaining: float, nextStop: RouteStop|null, etaMinutes: int|null, lastPingMinutes: int|null, isIdle: bool}
     */
    private function summarize(DeliveryRoute $route): array
    {
        $stops = $route->stops;
        $open = $stops->reject(fn (RouteStop $stop): bool => $stop->delivery->status->isFinished());
        $delivered = $stops->filter(fn (RouteStop $stop): bool => $stop->delivery->status === DeliveryStatus::Delivered);

        $nextStop = $this->nextStop($route);
        $location = $route->latestLocation;
        $lastPingMinutes = $location?->recorded_at?->diffInMinutes(now());

        $etaMinutes = null;
        if ($nextStop !== null && $location !== null && $nextStop->delivery->customer->hasCoordinates()) {
            $meters = RouteOptimizer::haversineMeters(
                ['lat' => $location->latitude, 'lng' => $location->longitude],
                $nextStop->delivery->customer->coordinates(),
            ) * RouteOptimizer::ROAD_FACTOR;
            $speed = ($location->speed_kmh ?? 0) >= 5 ? $location->speed_kmh : self::DEFAULT_SPEED_KMH;
            $etaMinutes = max(1, (int) round($meters / 1000 / $speed * 60));
        }

        return [
            'total' => $stops->count(),
            'delivered' => $delivered->count(),
            'failed' => $stops->filter(fn (RouteStop $stop): bool => $stop->delivery->status === DeliveryStatus::Failed)->count(),
            'amountDelivered' => (float) $delivered->sum(fn (RouteStop $stop): float => $stop->delivery->amount),
            'amountTotal' => (float) $stops->sum(fn (RouteStop $stop): float => $stop->delivery->amount),
            'kmRemaining' => $open->sum(fn (RouteStop $stop): int => $stop->distance_from_previous_m ?? 0) / 1000,
            'nextStop' => $nextStop,
            'etaMinutes' => $etaMinutes,
            'lastPingMinutes' => $lastPingMinutes === null ? null : (int) $lastPingMinutes,
            'isIdle' => $this->isIdle($route),
        ];
    }

    /**
     * Números da operação do dia inteiro, para a tela com todos os motoristas.
     *
     * @param  Collection<int, DeliveryRoute>  $routes
     * @return array{inProgress: int, routes: int, finished: int, stops: int, failed: int, idle: int, withoutSignal: int}
     */
    private function overview(Collection $routes): array
    {
        $inProgress = $routes->filter(fn (DeliveryRoute $route): bool => $route->status === DeliveryRouteStatus::InProgress);

        return [
            'inProgress' => $inProgress->count(),
            'routes' => $routes->count(),
            'finished' => (int) $routes->sum(fn (DeliveryRoute $route): int => $route->delivered_count + $route->failed_count),
            'stops' => (int) $routes->sum('stops_count'),
            'failed' => (int) $routes->sum('failed_count'),
            'idle' => $routes->filter(fn (DeliveryRoute $route): bool => $this->isIdle($route))->count(),
            'withoutSignal' => $inProgress->filter(fn (DeliveryRoute $route): bool => $route->latestLocation === null)->count(),
        ];
    }

    private function nextStop(DeliveryRoute $route): ?RouteStop
    {
        $open = $route->stops->reject(fn (RouteStop $stop): bool => $stop->delivery->status->isFinished());

        return $open->first(fn (RouteStop $stop): bool => $stop->delivery->status === DeliveryStatus::InTransit) ?? $open->first();
    }

    private function isIdle(DeliveryRoute $route): bool
    {
        return $route->status === DeliveryRouteStatus::InProgress
            && $route->latestLocation !== null
            && $route->latestLocation->recorded_at->lt(now()->subMinutes(self::IDLE_AFTER_MINUTES));
    }
}
