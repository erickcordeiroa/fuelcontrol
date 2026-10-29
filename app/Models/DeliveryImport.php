<?php

namespace App\Models;

use App\Enums\DeliveryImportStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DeliveryImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryImport extends Model
{
    /** @use HasFactory<DeliveryImportFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'file_name',
        'scheduled_for',
        'status',
        'total_rows',
        'imported_rows',
        'failed_rows',
        'errors',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'status' => DeliveryImportStatus::class,
            'total_rows' => 'integer',
            'imported_rows' => 'integer',
            'failed_rows' => 'integer',
            'errors' => 'array',
        ];
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
