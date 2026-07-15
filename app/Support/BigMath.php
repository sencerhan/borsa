<?php

namespace App\Support;

/**
 * Para/miktar hesapları için BCMath sarmalayıcı.
 * Float aritmetiği kesinlikle kullanılmaz; tüm değerler string olarak taşınır.
 */
final class BigMath
{
    /** Dahili hesaplama hassasiyeti (ondalık basamak) */
    public const SCALE = 18;

    public static function add(string $a, string $b): string
    {
        return bcadd(self::num($a), self::num($b), self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::num($a), self::num($b), self::SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul(self::num($a), self::num($b), self::SCALE);
    }

    public static function div(string $a, string $b): string
    {
        return bcdiv(self::num($a), self::num($b), self::SCALE);
    }

    /** a <=> b : -1, 0, 1 */
    public static function cmp(string $a, string $b): int
    {
        return bccomp(self::num($a), self::num($b), self::SCALE);
    }

    public static function gt(string $a, string $b): bool
    {
        return self::cmp($a, $b) > 0;
    }

    public static function gte(string $a, string $b): bool
    {
        return self::cmp($a, $b) >= 0;
    }

    public static function lt(string $a, string $b): bool
    {
        return self::cmp($a, $b) < 0;
    }

    public static function lte(string $a, string $b): bool
    {
        return self::cmp($a, $b) <= 0;
    }

    public static function eq(string $a, string $b): bool
    {
        return self::cmp($a, $b) === 0;
    }

    public static function isZero(string $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function isPositive(string $a): bool
    {
        return self::cmp($a, '0') > 0;
    }

    public static function min(string $a, string $b): string
    {
        return self::lte($a, $b) ? $a : $b;
    }

    public static function max(string $a, string $b): string
    {
        return self::gte($a, $b) ? $a : $b;
    }

    /** Yüzde oranı uygula: amount * rate */
    public static function percentOf(string $amount, string $rate): string
    {
        return self::mul($amount, $rate);
    }

    /** Değerin geçerli bir sayısal string olduğunu doğrular. */
    public static function isValid(string $value): bool
    {
        return is_numeric($value);
    }

    /**
     * Girdinin sayısal olduğunu garanti eder (BCMath yalnızca numeric-string kabul eder).
     *
     * @return numeric-string
     */
    private static function num(string $value): string
    {
        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Geçersiz sayısal değer: {$value}");
        }

        return $value;
    }
}
