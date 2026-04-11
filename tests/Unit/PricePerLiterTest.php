<?php

namespace Tests\Unit;

use App\Casts\PricePerLiterCast;
use App\Support\PricePerLiter;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class PricePerLiterTest extends TestCase
{
    public function test_price_per_liter_cast_persists_four_decimal_places(): void
    {
        $cast = new PricePerLiterCast;
        $model = new class extends Model
        {
            protected $guarded = [];
        };

        $this->assertSame(['price_per_liter' => 63_499], $cast->set($model, 'price_per_liter', '6,3499', []));
        $this->assertSame(6.3499, $cast->get($model, 'price_per_liter', 63_499, []));
    }

    public function test_fuel_cost_rounds_from_four_decimal_price_using_integer_precision(): void
    {
        $this->assertSame(226.5, PricePerLiter::fuelCost(35.67, 6.3499));
    }
}
