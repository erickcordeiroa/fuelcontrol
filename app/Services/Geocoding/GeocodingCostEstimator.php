<?php

namespace App\Services\Geocoding;

use Illuminate\Support\Carbon;

/**
 * Estima a fatura mensal do Google Geocoding (SKU Essentials) pela tabela em config/services.php.
 */
class GeocodingCostEstimator
{
    /**
     * @param  list<array{0: int|null, 1: float}>  $priceTiers  Limite superior de consultas no mês (null = sem limite) e preço em US$ a cada mil
     */
    public function __construct(
        public readonly int $freePerMonth,
        private readonly array $priceTiers,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('services.google_maps.geocoding_free_per_month'),
            config('services.google_maps.geocoding_price_tiers'),
        );
    }

    /**
     * Custo em US$ para um total de consultas cobráveis no mês.
     */
    public function costFor(int $billableRequests): float
    {
        $cost = 0.0;
        $lowerBound = $this->freePerMonth;

        foreach ($this->priceTiers as [$upperBound, $pricePerThousand]) {
            $upperBound ??= PHP_INT_MAX;

            if ($billableRequests > $lowerBound) {
                $cost += (min($billableRequests, $upperBound) - $lowerBound) * $pricePerThousand / 1000;
            }

            $lowerBound = max($lowerBound, $upperBound);
        }

        return round($cost, 2);
    }

    /**
     * Projeta o total do mês mantendo o ritmo diário observado até agora.
     */
    public function projectMonth(int $billableSoFar, Carbon $now): int
    {
        $daysElapsed = max(1, $now->day);

        return (int) ceil($billableSoFar / $daysElapsed * $now->daysInMonth);
    }

    public function freeRemaining(int $billableRequests): int
    {
        return max(0, $this->freePerMonth - $billableRequests);
    }
}
