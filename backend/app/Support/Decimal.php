<?php

namespace App\Support;

final class Decimal
{
    public static function hasFraction(string $value, int $scale = 6): bool
    {
        $normalized = bcadd($value, '0', $scale);

        return preg_match('/\.0*$/', $normalized) !== 1;
    }

    public static function roundHalfUp(string $value, int $scale): string
    {
        $multiplier = bcpow('10', (string) $scale, 0);
        $scaled = bcadd(bcmul($value, $multiplier, $scale), '0.5', $scale);

        return bcdiv(self::floorDecimal($scaled), $multiplier, $scale);
    }

    private static function floorDecimal(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        [$integer, $fraction] = explode('.', $value, 2);

        if (str_starts_with($integer, '-') && $fraction !== '' && (int) $fraction !== 0) {
            return bcsub($integer, '1', 0);
        }

        return $integer;
    }

    public static function trim(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }

    public static function fitsScale(string $value, int $scale): bool
    {
        $comparisonScale = max($scale, 6);

        return bccomp(
            bcadd($value, '0', $scale),
            bcadd($value, '0', $comparisonScale),
            $comparisonScale,
        ) === 0;
    }
}
