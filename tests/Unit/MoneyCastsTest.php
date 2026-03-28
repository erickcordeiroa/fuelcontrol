<?php

namespace Tests\Unit;

use App\Casts\MoneyBrlCentsCast;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class MoneyCastsTest extends TestCase
{
    public function test_money_brl_cents_cast_set_accepts_brazilian_string(): void
    {
        $cast = new MoneyBrlCentsCast;
        $model = new class extends Model
        {
            protected $guarded = [];
        };

        $this->assertSame(['amount' => 10_000], $cast->set($model, 'amount', '100,00', []));
        $this->assertSame(['amount' => 10_000], $cast->set($model, 'amount', 'R$ 100,00', []));
    }

    public function test_money_brl_cents_cast_set_accepts_price_per_liter_style_string(): void
    {
        $cast = new MoneyBrlCentsCast;
        $model = new class extends Model
        {
            protected $guarded = [];
        };

        $this->assertSame(['price_per_liter' => 630], $cast->set($model, 'price_per_liter', '6,30', []));
    }
}
