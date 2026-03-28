<?php

namespace App\Models;

use App\Casts\PricePerLiterMicrosCast;
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
        'price_per_liter',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_per_liter' => PricePerLiterMicrosCast::class,
        ];
    }

    /**
     * @return HasMany<Fuel, $this>
     */
    public function fuels(): HasMany
    {
        return $this->hasMany(Fuel::class);
    }
}
