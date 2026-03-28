<?php

namespace App\Casts;

use App\Support\BrazilianNumber;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Persist money as integer centavos; expose as float reais on the model.
 *
 * @implements CastsAttributes<float, float|int|string|null>
 */
class MoneyBrlCentsCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round(((int) $value) / 100, 2);
    }

    /**
     * @return array<string, int>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [$key => 0];
        }

        $float = $this->toFloat($value);

        return [$key => (int) round($float * 100)];
    }

    private function toFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return 0.0;
            }

            if (is_numeric($trimmed)) {
                return (float) $trimmed;
            }

            return BrazilianNumber::parse($trimmed);
        }

        return 0.0;
    }
}
