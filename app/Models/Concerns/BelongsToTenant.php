<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * @property int|null $user_id Tenant owner (dono dos dados)
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if (! Auth::check()) {
                return;
            }

            if ($model->getAttribute('user_id') === null) {
                $model->setAttribute('user_id', Auth::user()->tenantOwnerId());
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function tenantOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
