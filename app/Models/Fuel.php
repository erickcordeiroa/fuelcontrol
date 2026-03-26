<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fuel extends Model
{
    /** @use HasFactory<\Database\Factories\FuelFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'liters',
        'price_per_liter',
        'station',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'liters' => 'decimal:2',
            'price_per_liter' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
