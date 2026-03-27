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

#[Fillable(['name', 'company_name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

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
