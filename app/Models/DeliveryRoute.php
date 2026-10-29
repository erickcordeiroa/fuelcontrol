<?php

namespace App\Models;

use App\Enums\DeliveryRouteStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DeliveryRouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
     * Centro de distribuição configurado em services.routing (ROUTING_DEPOT_*).
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function defaultOrigin(): ?array
    {
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
