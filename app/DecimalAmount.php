<?php

namespace App;

class DecimalAmount
{
    public static function units(string $amount, int $scale): int
    {
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $amount)) {
            throw new \InvalidArgumentException('Invalid decimal amount.');
        }

        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '');

        if (strlen($fraction) > $scale && trim(substr($fraction, $scale), '0') !== '') {
            throw new \InvalidArgumentException('Too many decimal places.');
        }

        $units = ((int) $whole * (10 ** $scale)) + (int) str_pad(substr($fraction, 0, $scale), $scale, '0');

        return $negative ? -$units : $units;
    }

    public static function format(int $units, int $scale = 2): string
    {
        $factor = 10 ** $scale;
        $negative = $units < 0 ? '-' : '';
        $absolute = abs($units);

        return $negative.intdiv($absolute, $factor).'.'.str_pad((string) ($absolute % $factor), $scale, '0', STR_PAD_LEFT);
    }
}
