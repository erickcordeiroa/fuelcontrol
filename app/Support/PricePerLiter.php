<?php

namespace App\Support;

class PricePerLiter
{
    public const DECIMALS = 4;

    private const STORAGE_MULTIPLIER = 10000;

    private const LITERS_MULTIPLIER = 100;

    public static function normalize(float|int|string|null $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_string($value)) {
            return round(BrazilianNumber::parse($value), self::DECIMALS);
        }

        return round((float) $value, self::DECIMALS);
    }

    public static function format(float|int|string|null $value): string
    {
        return BrazilianNumber::format(self::normalize($value), self::DECIMALS);
    }

    public static function fromStorage(int|string|null $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round(((int) $value) / self::STORAGE_MULTIPLIER, self::DECIMALS);
    }

    public static function toStorage(float|int|string|null $value): int
    {
        return (int) round(self::normalize($value) * self::STORAGE_MULTIPLIER);
    }

    public static function equals(float|int|string|null $first, float|int|string|null $second): bool
    {
        return self::toStorage($first) === self::toStorage($second);
    }

    public static function fuelCost(float|int|string|null $liters, float|int|string|null $pricePerLiter): float
    {
        $litersMinor = (int) round((float) $liters * self::LITERS_MULTIPLIER);
        $priceMinor = self::toStorage($pricePerLiter);

        return round(($litersMinor * $priceMinor) / (self::LITERS_MULTIPLIER * self::STORAGE_MULTIPLIER), 2);
    }
}
