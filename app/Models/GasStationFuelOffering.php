<?php

namespace App\Models;

use App\Casts\MoneyBrlCentsCast;
use App\Enums\FuelType;
use Database\Factories\GasStationFuelOfferingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GasStationFuelOffering extends Model
{
    /** @use HasFactory<GasStationFuelOfferingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gas_station_id',
        'fuel_type',
        'price_per_liter',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fuel_type' => FuelType::class,
            'price_per_liter' => MoneyBrlCentsCast::class,
        ];
    }

    /**
     * @return BelongsTo<GasStation, $this>
     */
    public function gasStation(): BelongsTo
    {
        return $this->belongsTo(GasStation::class);
    }

    /**
     * @return HasMany<Fuel, $this>
     */
    public function tripFuels(): HasMany
    {
        return $this->hasMany(Fuel::class, 'gas_station_fuel_offering_id');
    }
}
