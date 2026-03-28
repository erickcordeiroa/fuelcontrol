<?php

use App\Support\BrazilianNumber;

if (! function_exists('format_money_brl')) {
    /**
     * Format a monetary value in Brazilian Real for display (prefix "R$ ", thousands ".", decimal ",").
     *
     * @param  float|int|string|null  $amountInReais  Value in reais (not centavos).
     */
    function format_money_brl(float|int|string|null $amountInReais, int $decimals = 2): string
    {
        $n = $amountInReais === null || $amountInReais === '' ? 0.0 : (float) $amountInReais;

        return 'R$ '.BrazilianNumber::format($n, $decimals);
    }
}
