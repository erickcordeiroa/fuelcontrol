<?php

namespace App\Models;

use Database\Factories\RouteStopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parada na sequência otimizada. O tenant vem da rota (delivery_routes.user_id).
 */
class RouteStop extends Model
{
    /** @use HasFactory<RouteStopFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'delivery_route_id',
        'delivery_id',
        'sequence',
        'distance_from_previous_m',
        'duration_from_previous_s',
        'path_polyline',
        'navigation_steps',
        'arrived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'distance_from_previous_m' => 'integer',
            'duration_from_previous_s' => 'integer',
            'navigation_steps' => 'array',
            'arrived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DeliveryRoute, $this>
     */
    public function deliveryRoute(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class);
    }

    /**
     * @return BelongsTo<Delivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
