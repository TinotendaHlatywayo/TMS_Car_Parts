<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    /**
     * ISO 4217 currencies whose minor unit is not two decimal places.
     * Unlisted currencies use the common two-decimal default and should be
     * verified before production use.
     *
     * @var array<string, int>
     */
    private const EXPONENTS = [
        'BHD' => 3,
        'CLP' => 0,
        'IQD' => 3,
        'ISK' => 0,
        'JOD' => 3,
        'JPY' => 0,
        'KRW' => 0,
        'KWD' => 3,
        'OMR' => 3,
        'PYG' => 0,
        'RWF' => 0,
        'TND' => 3,
        'UGX' => 0,
        'VND' => 0,
        'XAF' => 0,
        'XOF' => 0,
        'XPF' => 0,
    ];

    public static function exponent(string $currency): int
    {
        return self::EXPONENTS[strtoupper($currency)] ?? 2;
    }

    public static function toMinor(string|int|float $amount, string $currency): int
    {
        $amount = trim((string) $amount);
        $exponent = self::exponent($currency);

        if (! preg_match('/^\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException('Amount must be a non-negative number.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        if (strlen($fraction) > $exponent) {
            throw new InvalidArgumentException("Amount has more than {$exponent} decimal places for {$currency}.");
        }

        $fraction = str_pad($fraction, $exponent, '0');
        $digits = ltrim($whole.$fraction, '0');
        $digits = $digits === '' ? '0' : $digits;

        if (strlen($digits) > 18) {
            throw new InvalidArgumentException('Amount is too large.');
        }

        return (int) $digits;
    }

    public static function format(int $minor, string $currency): string
    {
        $exponent = self::exponent($currency);

        if ($exponent === 0) {
            return (string) $minor;
        }

        $sign = $minor < 0 ? '-' : '';
        $digits = str_pad((string) abs($minor), $exponent + 1, '0', STR_PAD_LEFT);

        return $sign.substr($digits, 0, -$exponent).'.'.substr($digits, -$exponent);
    }
}
