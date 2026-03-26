<?php

namespace App\Models;

use App\Enums\ExpenseType;
use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Trip extends Model
{
    /** @use HasFactory<\Database\Factories\TripFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'date',
        'vehicle_id',
        'driver_id',
        'km_start',
        'km_end',
        'km_total',
        'revenue',
        'status',
    ];

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
            'revenue' => 'decimal:2',
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

    public function fuelCost(): float
    {
        if ($this->fuel === null) {
            return 0.0;
        }

        return round((float) $this->fuel->liters * (float) $this->fuel->price_per_liter, 2);
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
