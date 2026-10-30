<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use Auditable, BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'license_number',
        'phone',
        'linked_user_id',
    ];

    /**
     * Celular só com dígitos: é o login do app do motorista (spec 004).
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return $digits === '' ? null : $digits;
    }

    /**
     * Celular para exibir: (13) 99722-1580.
     */
    public function formattedPhone(): ?string
    {
        $digits = (string) $this->phone;

        if (strlen($digits) < 10) {
            return $this->phone;
        }

        return '('.substr($digits, 0, 2).') '.substr($digits, 2, -4).'-'.substr($digits, -4);
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => self::normalizePhone($value));
    }

    /**
     * Usuário que faz login como motorista (opcional).
     *
     * @return BelongsTo<User, $this>
     */
    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    /**
     * @return HasMany<Trip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
