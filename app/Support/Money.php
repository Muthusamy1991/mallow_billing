<?php

namespace App\Support;

final class Money
{
    public static function add(string $left, string $right, int $scale = 4): string
    {
        return bcadd($left, $right, $scale);
    }

    public static function mul(string $left, string $right, int $scale = 4): string
    {
        return bcmul($left, $right, $scale);
    }

    public static function div(string $left, string $right, int $scale = 8): string
    {
        if (bccomp($right, '0', $scale) === 0) {
            return '0.'.str_repeat('0', $scale);
        }

        return bcdiv($left, $right, $scale);
    }

    public static function round(string $amount, int $precision = 2): string
    {
        $pad = '0.'.str_repeat('0', $precision).'5';

        if (bccomp($amount, '0', $precision + 1) >= 0) {
            return bcadd($amount, $pad, $precision);
        }

        return bcsub($amount, $pad, $precision);
    }

    public static function format(string $amount, int $precision = 2): string
    {
        return number_format((float) self::round($amount, $precision), $precision, '.', ',');
    }
}
