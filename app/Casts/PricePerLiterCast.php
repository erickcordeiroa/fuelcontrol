<?php

namespace App\Casts;

use App\Support\PricePerLiter;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Persist fuel price per liter as integer micros of real; expose as float reais on the model.
 *
 * @implements CastsAttributes<float, float|int|string|null>
 */
class PricePerLiterCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): float
    {
        return PricePerLiter::fromStorage(is_scalar($value) ? (string) $value : null);
    }

    /**
     * @return array<string, int>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [$key => PricePerLiter::toStorage($value)];
    }
}
