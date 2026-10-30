<?php

namespace Tests\Unit;

use App\Services\Geocoding\GeocodingCostEstimator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class GeocodingCostEstimatorTest extends TestCase
{
    private GeocodingCostEstimator $estimator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estimator = new GeocodingCostEstimator(10_000, [
            [100_000, 5.00],
            [500_000, 4.00],
            [null, 3.00],
        ]);
    }

    public function test_usage_within_free_tier_costs_nothing(): void
    {
        $this->assertSame(0.0, $this->estimator->costFor(0));
        $this->assertSame(0.0, $this->estimator->costFor(10_000));
    }

    public function test_charges_only_requests_above_free_tier(): void
    {
        $this->assertSame(25.0, $this->estimator->costFor(15_000));
        $this->assertSame(450.0, $this->estimator->costFor(100_000));
    }

    public function test_applies_each_volume_tier_to_its_own_range(): void
    {
        // 90.000 × US$ 5 + 50.000 × US$ 4 (a cada mil)
        $this->assertSame(650.0, $this->estimator->costFor(150_000));

        // 90.000 × 5 + 400.000 × 4 + 100.000 × 3 (faixa sem limite)
        $this->assertSame(2_350.0, $this->estimator->costFor(600_000));
    }

    public function test_free_remaining_never_goes_negative(): void
    {
        $this->assertSame(7_000, $this->estimator->freeRemaining(3_000));
        $this->assertSame(0, $this->estimator->freeRemaining(12_000));
    }

    public function test_projects_month_from_daily_pace(): void
    {
        $this->assertSame(3_100, $this->estimator->projectMonth(1_000, Carbon::create(2026, 10, 10)));
        $this->assertSame(0, $this->estimator->projectMonth(0, Carbon::create(2026, 10, 10)));
    }
}
