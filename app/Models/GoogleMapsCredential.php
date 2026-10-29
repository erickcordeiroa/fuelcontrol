<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\GoogleMapsCredentialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Chave da Google Geocoding API cadastrada pela empresa. Cada empresa usa (e paga) a própria conta no Google.
 */
class GoogleMapsCredential extends Model
{
    /** @use HasFactory<GoogleMapsCredentialFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'api_key',
        'key_hint',
        'verified_at',
        'last_error',
        'last_checked_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'api_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public static function forTenant(?int $tenantId): ?self
    {
        if ($tenantId === null) {
            return null;
        }

        return self::query()->withoutGlobalScopes()->where('user_id', $tenantId)->first();
    }

    /**
     * Guarda a chave nova e zera a verificação anterior.
     */
    public static function store(int $tenantId, string $apiKey): self
    {
        return self::query()->withoutGlobalScopes()->updateOrCreate(
            ['user_id' => $tenantId],
            [
                'api_key' => $apiKey,
                'key_hint' => mb_substr($apiKey, -4),
                'verified_at' => null,
                'last_error' => null,
                'last_checked_at' => null,
            ],
        );
    }

    public function maskedKey(): string
    {
        return '••••••••'.$this->key_hint;
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null && $this->last_error === null;
    }
}
