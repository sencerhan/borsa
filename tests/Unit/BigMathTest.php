<?php

namespace Tests\Unit;

use App\Support\BigMath;
use PHPUnit\Framework\TestCase;

class BigMathTest extends TestCase
{
    public function test_basic_arithmetic_is_18_decimal_exact(): void
    {
        $this->assertSame('0.300000000000000000', BigMath::add('0.1', '0.2'));
        $this->assertSame('0.100000000000000000', BigMath::sub('0.3', '0.2'));
        $this->assertSame('30.000000000000000000', BigMath::mul('0.03', '1000'));
        // bcdiv truncates (round yok)
        $this->assertSame('0.333333333333333333', BigMath::div('1', '3'));
    }

    public function test_comparisons(): void
    {
        $this->assertSame(1, BigMath::cmp('2', '1'));
        $this->assertSame(-1, BigMath::cmp('1', '2'));
        $this->assertSame(0, BigMath::cmp('1.0', '1.000'));
        $this->assertTrue(BigMath::gt('2', '1'));
        $this->assertTrue(BigMath::gte('1', '1'));
        $this->assertTrue(BigMath::lt('1', '2'));
        $this->assertTrue(BigMath::lte('1', '1'));
        $this->assertTrue(BigMath::eq('1.5', '1.50'));
    }

    public function test_zero_and_positive_and_minmax(): void
    {
        $this->assertTrue(BigMath::isZero('0.0'));
        $this->assertFalse(BigMath::isPositive('0'));
        $this->assertTrue(BigMath::isPositive('0.000000000000000001'));
        $this->assertSame('1', BigMath::min('1', '2'));
        $this->assertSame('2', BigMath::max('1', '2'));
    }

    public function test_percent_of(): void
    {
        // 30 * 0.001 (%0.1) = 0.03
        $this->assertSame('0.030000000000000000', BigMath::percentOf('30', '0.001'));
    }

    public function test_is_valid(): void
    {
        $this->assertTrue(BigMath::isValid('1.5'));
        $this->assertTrue(BigMath::isValid('-3'));
        $this->assertFalse(BigMath::isValid('abc'));
        $this->assertFalse(BigMath::isValid(''));
    }

    public function test_non_numeric_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BigMath::add('1', 'x');
    }
}
