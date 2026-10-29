<?php

namespace App\Models;

use App\Casts\MoneyBrlCentsCast;
use App\Enums\DeliveryFailureReason;
use App\Enums\DeliveryStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'delivery_import_id',
        'customer_id',
        'order_number',
        'scheduled_for',
        'amount',
        'sector',
        'window_start',
        'window_end',
        'status',
        'delivered_at',
        'failure_reason',
        'failure_reason_code',
        'failure_note',
        'completed_by_driver_id',
        'completed_latitude',
        'completed_longitude',
        'completed_accuracy_m',
        'client_uuid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'amount' => MoneyBrlCentsCast::class,
            'status' => DeliveryStatus::class,
            'delivered_at' => 'datetime',
            'failure_reason_code' => DeliveryFailureReason::class,
            'completed_latitude' => 'float',
            'completed_longitude' => 'float',
            'completed_accuracy_m' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<DeliveryImport, $this>
     */
    public function deliveryImport(): BelongsTo
    {
        return $this->belongsTo(DeliveryImport::class);
    }

    /**
     * @return HasOne<RouteStop, $this>
     */
    public function routeStop(): HasOne
    {
        return $this->hasOne(RouteStop::class);
    }

    /**
     * Pedidos ainda sem rota.
     *
     * @param  Builder<Delivery>  $query
     */
    public function scopeUnrouted(Builder $query): void
    {
        $query->where('status', DeliveryStatus::Pending)->whereDoesntHave('routeStop');
    }
}
