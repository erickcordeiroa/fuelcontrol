<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'company_name', 'depot_address', 'depot_latitude', 'depot_longitude', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'depot_latitude' => 'float',
            'depot_longitude' => 'float',
            'role' => UserRole::class,
        ];
    }

    /**
     * Cadastro de motorista vinculado a este acesso.
     *
     * @return HasOne<Driver, $this>
     */
    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class, 'linked_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * ID do usuário dono do tenant (frotas, viagens, etc.). Admin = próprio id; motorista = user_id do cadastro de motorista.
     */
    /**
     * Centro de distribuição da empresa (só no usuário dono dos dados).
     *
     * @return array{lat: float, lng: float}|null
     */
    public function depot(): ?array
    {
        if ($this->depot_latitude === null || $this->depot_longitude === null) {
            return null;
        }

        return ['lat' => (float) $this->depot_latitude, 'lng' => (float) $this->depot_longitude];
    }

    public function tenantOwnerId(): int
    {
        if ($this->isAdmin()) {
            return $this->id;
        }

        $driver = Driver::withoutGlobalScopes()->where('linked_user_id', $this->id)->first();

        if ($driver !== null && $driver->user_id !== null) {
            return (int) $driver->user_id;
        }

        return $this->id;
    }
}
