<?php

namespace App\Shared\Support;

/**
 * Decimal-string money arithmetic. Never use float for money.
 */
final class Money
{
    public static function add(string ...$amounts): string
    {
        $total = '0.00';

        foreach ($amounts as $amount) {
            $total = bcadd($total, $amount, 2);
        }

        return $total;
    }

    public static function sub(string $left, string $right): string
    {
        return bcsub($left, $right, 2);
    }

    public static function mul(string $left, string $right): string
    {
        return bcmul($left, $right, 2);
    }

    /**
     * Compare two decimal strings. Returns -1, 0 or 1 (like bccomp).
     */
    public static function cmp(string $left, string $right): int
    {
        return bccomp($left, $right, 2);
    }
}
