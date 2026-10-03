<?php

namespace App\Support;

final class ProjectionMath
{
    public static function rate(float $points, int $games): float
    {
        if ($games < 0 || !is_finite($points)) throw new \InvalidArgumentException('Invalid fantasy points or games played.');
        return $games === 0 ? 0.0 : $points / $games;
    }

    public static function average(float $baseline, float $seven, float $fourteen, float $twentyOne): float
    {
        return ($baseline + $seven + $fourteen + $twentyOne) / 4;
    }
}
