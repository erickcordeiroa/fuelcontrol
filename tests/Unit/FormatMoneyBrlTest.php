<?php

namespace Tests\Unit;

use Tests\TestCase;

class FormatMoneyBrlTest extends TestCase
{
    public function test_format_money_brl_formats_reais_with_prefix_and_pt_br_separators(): void
    {
        $this->assertSame('R$ 1.234,56', format_money_brl(1234.56));
        $this->assertSame('R$ 0,00', format_money_brl(null));
        $this->assertSame('R$ 6,2990', format_money_brl(6.299, 4));
    }
}
