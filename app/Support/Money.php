<?php

namespace App\Support;

/**
 * Money is always handled as integer minor units (LKR cents). Never floats.
 */
final class Money
{
    public static function format(?int $minor): ?string
    {
        if ($minor === null) {
            return null;
        }
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);

        return $sign.number_format(intdiv($abs, 100)).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Convert a decimal string such as "1250.50" to minor units without float arithmetic.
     */
    public static function toMinor(string|int $amount): int
    {
        $amount = trim((string) $amount);
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new \InvalidArgumentException('Invalid money amount.');
        }
        [$whole, $fraction] = array_pad(explode('.', $amount), 2, '0');

        return ((int) $whole) * 100 + (int) str_pad($fraction, 2, '0');
    }

    /**
     * Integer division rounded half-up.
     */
    public static function divRound(int $numerator, int $denominator): int
    {
        return intdiv($numerator * 2 + $denominator, $denominator * 2);
    }
}
