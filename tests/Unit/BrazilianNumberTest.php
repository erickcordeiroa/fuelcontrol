<?php

namespace Tests\Unit;

use App\Support\BrazilianNumber;
use PHPUnit\Framework\TestCase;

class BrazilianNumberTest extends TestCase
{
    public function test_parse_handles_brazilian_thousands_and_comma_decimal(): void
    {
        $this->assertSame(1234.56, BrazilianNumber::parse('1.234,56'));
    }

    public function test_parse_handles_simple_comma_decimal(): void
    {
        $this->assertSame(10.5, BrazilianNumber::parse('10,5'));
    }

    public function test_parse_empty_returns_zero(): void
    {
        $this->assertSame(0.0, BrazilianNumber::parse(''));
        $this->assertSame(0.0, BrazilianNumber::parse('   '));
    }

    public function test_format_outputs_pt_br(): void
    {
        $this->assertSame('1.234,56', BrazilianNumber::format(1234.56, 2));
    }
}
