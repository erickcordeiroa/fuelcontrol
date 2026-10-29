<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de auditoria (spec 009): empresa + metadados em JSON. Somente inclusão.
 *
 * @property array{event: string, model: string, model_id: int|string|null, changes: array<string, array{old: mixed, new: mixed}>, actor: array{id: int, name: string, role: string|null}|null, source: string, ip: string|null} $metadata
 */
class AuditLog extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        /** O log não pode ser alterado nem apagado pela aplicação. */
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }
}
