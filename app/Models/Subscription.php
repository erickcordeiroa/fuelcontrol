<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Plano contratado pela empresa (spec 007). A cobrança entra na spec 008.
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    public const STATUS_ACTIVE = 'active';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'custom_limits',
        'started_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_limits' => 'array',
            'started_at' => 'datetime',
        ];
    }
}
