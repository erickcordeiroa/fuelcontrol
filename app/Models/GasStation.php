<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\GasStationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GasStation extends Model
{
    /** @use HasFactory<GasStationFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'address',
    ];

    /**
     * @return HasMany<GasStationFuelOffering, $this>
     */
    public function fuelOfferings(): HasMany
    {
        return $this->hasMany(GasStationFuelOffering::class)->orderBy('fuel_type');
    }

    /**
     * Registros de abastecimento de viagens vinculados a este posto.
     *
     * @return HasMany<Fuel, $this>
     */
    public function tripFuels(): HasMany
    {
        return $this->hasMany(Fuel::class);
    }
}
