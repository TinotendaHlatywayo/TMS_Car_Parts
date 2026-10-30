<?php

namespace Tests\Unit;

use App\Support\Decimal;
use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_usd_is_stored_in_minor_units(): void
    {
        $this->assertSame(1234, Money::toMinor('12.34', 'USD'));
        $this->assertSame('12.34', Money::format(1234, 'USD'));
    }

    public function test_zero_decimal_currency_is_supported(): void
    {
        $this->assertSame(1200, Money::toMinor('1200', 'JPY'));
        $this->assertSame('1200', Money::format(1200, 'JPY'));
    }

    public function test_three_decimal_currency_is_supported(): void
    {
        $this->assertSame(1234, Money::toMinor('1.234', 'KWD'));
        $this->assertSame('1.234', Money::format(1234, 'KWD'));
    }

    public function test_excess_currency_precision_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toMinor('1.234', 'USD');
    }

    public function test_negative_and_non_numeric_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toMinor('-1.00', 'USD');
    }

    public function test_decimal_helpers_trim_and_round_half_up(): void
    {
        $this->assertSame('2.5', Decimal::trim('2.500000'));
        $this->assertSame('2', Decimal::trim('2.000000'));
        $this->assertSame('0.333334', Decimal::roundHalfUp('0.3333335', 6));
        $this->assertTrue(Decimal::hasFraction('1.25'));
        $this->assertFalse(Decimal::hasFraction('1.0000'));
    }
}
