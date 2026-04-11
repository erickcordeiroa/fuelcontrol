<?php

namespace App\Models;

use App\Casts\MoneyBrlCentsCast;
use App\Enums\ExpenseType;
use App\Enums\TripStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\PricePerLiter;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'trip_time',
        'notes',
        'vehicle_id',
        'driver_id',
        'km_start',
        'km_end',
        'km_total',
        'revenue',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (Trip $trip): void {
            if ($trip->user_id !== null) {
                return;
            }

            if ($trip->vehicle_id === null) {
                return;
            }

            $uid = Vehicle::withoutGlobalScopes()->find($trip->vehicle_id)?->user_id;
            if ($uid !== null) {
                $trip->user_id = (int) $uid;
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
            'km_start' => 'integer',
            'km_end' => 'integer',
            'km_total' => 'integer',
            'revenue' => MoneyBrlCentsCast::class,
            'status' => TripStatus::class,
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
     * @return HasOne<Fuel, $this>
     */
    public function fuel(): HasOne
    {
        return $this->hasOne(Fuel::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return HasMany<TripChangeLog, $this>
     */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(TripChangeLog::class);
    }

    public function fuelCost(): float
    {
        if ($this->fuel === null) {
            return 0.0;
        }

        return PricePerLiter::fuelCost((float) $this->fuel->liters, (float) $this->fuel->price_per_liter);
    }

    public function otherExpensesTotal(): float
    {
        return round((float) $this->expenses->sum('amount'), 2);
    }

    public function expenseAmountFor(ExpenseType $type): float
    {
        return round((float) ($this->expenses->firstWhere('type', $type)?->amount ?? 0), 2);
    }

    public function netMargin(): float
    {
        return round((float) $this->revenue - $this->fuelCost() - $this->otherExpensesTotal(), 2);
    }

    public function operationalCost(): float
    {
        return round($this->fuelCost() + $this->otherExpensesTotal(), 2);
    }

    public function fuelEfficiencyKmPerLiter(): ?float
    {
        if ($this->fuel === null || (float) $this->fuel->liters <= 0) {
            return null;
        }

        return round($this->km_total / (float) $this->fuel->liters, 2);
    }

    public function fuelCostPerKm(): ?float
    {
        if ($this->km_total <= 0) {
            return null;
        }

        return round($this->fuelCost() / $this->km_total, 2);
    }
}
