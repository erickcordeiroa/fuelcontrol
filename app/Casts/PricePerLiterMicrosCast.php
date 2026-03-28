<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Persist price per liter as integer ten-thousandths of a real (4 decimal places); expose as float reais/L on the model.
 *
 * @implements CastsAttributes<float, float|int|string|null>
 */
class PricePerLiterMicrosCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round(((int) $value) / 10000, 4);
    }

    /**
     * @return array<string, int>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [$key => 0];
        }

        $float = is_numeric($value) ? (float) $value : 0.0;

        return [$key => (int) round($float * 10000)];
    }
}
