<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\OilChangeAlertService;
use Carbon\Carbon;
use Database\Factories\OilChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class OilChange extends Model
{
    /** @use HasFactory<OilChangeFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'vehicle_id',
        'date',
        'occurred_at',
        'km_at_change',
        'oil_brand',
        'interval_km',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (OilChange $model): void {
            if ($model->occurred_at !== null || $model->date === null) {
                return;
            }

            $model->occurred_at = Carbon::parse($model->date)->setTimeFrom(now());
        });

        static::saved(function (): void {
            if (Auth::check()) {
                OilChangeAlertService::forgetCacheForCurrentTenant();
            }
        });

        static::deleted(function (): void {
            if (Auth::check()) {
                OilChangeAlertService::forgetCacheForCurrentTenant();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'occurred_at' => 'datetime',
            'km_at_change' => 'integer',
            'interval_km' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
