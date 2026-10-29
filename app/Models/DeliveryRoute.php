<?php

namespace App\Models;

use App\Enums\DeliveryRouteStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Services\Routing\RoutePlan;
use Database\Factories\DeliveryRouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

class DeliveryRoute extends Model
{
    /** @use HasFactory<DeliveryRouteFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'vehicle_id',
        'driver_id',
        'name',
        'scheduled_for',
        'status',
        'origin_latitude',
        'origin_longitude',
        'total_distance_m',
        'total_duration_s',
        'routing_provider',
        'order_source',
        'routing_note',
        'return_path_polyline',
        'optimized_at',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'status' => DeliveryRouteStatus::class,
            'origin_latitude' => 'float',
            'origin_longitude' => 'float',
            'total_distance_m' => 'integer',
            'total_duration_s' => 'integer',
            'optimized_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return HasMany<RouteStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<DriverLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(DriverLocation::class);
    }

    /**
     * @return HasOne<DriverLocation, $this>
     */
    public function latestLocation(): HasOne
    {
        return $this->hasOne(DriverLocation::class)->latestOfMany('recorded_at');
    }

    /**
     * Aviso para o administrador quando a rota não saiu otimizada ou pelas ruas, com o motivo.
     */
    public function routingNotice(): ?string
    {
        return match ($this->routing_note) {
            RoutePlan::NOTE_ORDER_DAILY_LIMIT => __('O limite diário de créditos do serviço de rotas não cobria a otimização: a ordem foi calculada pelo Kilomo e o trajeto saiu pelas ruas. Reotimize amanhã para otimizar pelo serviço.'),
            RoutePlan::NOTE_DAILY_LIMIT => __('O limite diário de créditos do serviço de rotas acabou: a rota foi traçada em linha reta. Reotimize amanhã para traçar pelas ruas.'),
            RoutePlan::NOTE_SERVICE_FAILED => __('O serviço de rotas não respondeu: a rota foi traçada em linha reta. Reotimize daqui a pouco para traçar pelas ruas.'),
            default => null,
        };
    }

    public function isRoutedByRoads(): bool
    {
        return $this->routing_provider === RoutePlan::PROVIDER_GEOAPIFY;
    }

    /**
     * Distância (e tempo, quando calculado pelas ruas) para as listas de rotas.
     */
    public function distanceSummary(): string
    {
        $km = number_format(($this->total_distance_m ?? 0) / 1000, 1, ',', '.');

        if (! $this->isRoutedByRoads()) {
            return __(':km km estimados em linha reta', ['km' => $km]);
        }

        $minutes = (int) ceil(($this->total_duration_s ?? 0) / 60);
        $duration = $minutes >= 60 ? intdiv($minutes, 60).' h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).' min' : $minutes.' min';

        return __(':km km pelas ruas · :duration de direção', ['km' => $km, 'duration' => $duration]);
    }

    /**
     * Centro de distribuição da empresa (cadastrado na tela de entregas); sem cadastro, o da plataforma em
     * services.routing (ROUTING_DEPOT_*), se houver.
     *
     * @param  int|null  $tenantId  Empresa; sem informar, a do usuário logado
     * @return array{lat: float, lng: float}|null
     */
    public static function defaultOrigin(?int $tenantId = null): ?array
    {
        $tenantId ??= Auth::user()?->tenantOwnerId();
        $companyDepot = $tenantId === null ? null : User::query()->find($tenantId)?->depot();

        if ($companyDepot !== null) {
            return $companyDepot;
        }

        $latitude = config('services.routing.depot_latitude');
        $longitude = config('services.routing.depot_longitude');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return ['lat' => (float) $latitude, 'lng' => (float) $longitude];
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function origin(): ?array
    {
        if ($this->origin_latitude === null || $this->origin_longitude === null) {
            return null;
        }

        return ['lat' => $this->origin_latitude, 'lng' => $this->origin_longitude];
    }
}
