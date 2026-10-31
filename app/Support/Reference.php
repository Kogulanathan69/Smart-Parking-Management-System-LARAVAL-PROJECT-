<?php

namespace App\Support;

final class Reference
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * Human-friendly unique-ish reference, e.g. "PS-261031-7KQ2MX". Uniqueness is enforced by DB indexes.
     */
    public static function make(string $prefix, int $length = 6): string
    {
        $random = '';
        for ($i = 0; $i < $length; $i++) {
            $random .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $prefix.'-'.now(config('parking.business_timezone'))->format('ymd').'-'.$random;
    }
}
