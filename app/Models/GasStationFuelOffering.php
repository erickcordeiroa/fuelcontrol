<?php

namespace App\Models;

use App\Casts\PricePerLiterCast;
use App\Enums\FuelType;
use App\Models\Concerns\Auditable;
use Database\Factories\GasStationFuelOfferingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GasStationFuelOffering extends Model
{
    /** @use HasFactory<GasStationFuelOfferingFactory> */
    use Auditable, HasFactory;

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
            'price_per_liter' => PricePerLiterCast::class,
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

    /**
     * O preço por combustível pertence à empresa dona do posto.
     */
    public function auditTenantId(): ?int
    {
        $tenantId = GasStation::query()->withoutGlobalScopes()->whereKey($this->gas_station_id)->value('user_id');

        return $tenantId === null ? null : (int) $tenantId;
    }
}
