<?php

namespace App\Models;

use App\Casts\PricePerLiterMicrosCast;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FuelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fuel extends Model
{
    /** @use HasFactory<FuelFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'trip_id',
        'gas_station_id',
        'liters',
        'price_per_liter',
        'station',
    ];

    protected static function booted(): void
    {
        static::creating(function (Fuel $fuel): void {
            if ($fuel->user_id !== null) {
                return;
            }

            if ($fuel->trip_id === null) {
                return;
            }

            $uid = Trip::withoutGlobalScopes()->find($fuel->trip_id)?->user_id;
            if ($uid !== null) {
                $fuel->user_id = (int) $uid;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'liters' => 'decimal:2',
            'price_per_liter' => PricePerLiterMicrosCast::class,
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<GasStation, $this>
     */
    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }
}
