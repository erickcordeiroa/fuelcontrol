<?php

namespace App\Models;

use App\Enums\ExpenseType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'trip_id',
        'type',
        'amount',
    ];

    protected static function booted(): void
    {
        static::creating(function (Expense $expense): void {
            if ($expense->user_id !== null) {
                return;
            }

            if ($expense->trip_id === null) {
                return;
            }

            $uid = Trip::withoutGlobalScopes()->find($expense->trip_id)?->user_id;
            if ($uid !== null) {
                $expense->user_id = (int) $uid;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExpenseType::class,
            'amount' => 'decimal:2',
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
