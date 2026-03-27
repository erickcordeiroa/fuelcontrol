<?php

namespace App\Support;

class BrazilianNumber
{
    /**
     * Parse Brazilian-formatted decimal input (e.g. "1.234,56" or "10,5") to float.
     */
    public static function parse(?string $input): float
    {
        if ($input === null) {
            return 0.0;
        }

        $s = trim(str_replace(' ', '', $input));
        if ($s === '') {
            return 0.0;
        }

        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        return round((float) $s, 6);
    }

    /**
     * Format a float for display with comma decimal and dot thousands (pt-BR).
     */
    public static function format(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, ',', '.');
    }
}
