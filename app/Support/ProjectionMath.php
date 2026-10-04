<?php

namespace App\Support;

final class ProjectionMath
{
    public const DEFAULT_WEIGHTS = ['fantrax'=>50.0, 'season'=>0.0, '7d'=>25.0, '14d'=>15.0, '21d'=>10.0];

    public static function weighted(array $rates, array $weights): float
    {
        $result = 0.0;
        foreach (self::DEFAULT_WEIGHTS as $key => $_) $result += (float)($rates[$key] ?? 0) * $weights[$key] / 100;
        return $result;
    }

    public static function rate(float $points, int $games): float
    {
        if ($games < 0 || !is_finite($points)) throw new \InvalidArgumentException('Invalid fantasy points or games played.');
        return $games === 0 ? 0.0 : $points / $games;
    }

    public static function average(float $baseline, float $seven, float $fourteen, float $twentyOne): float
    {
        return self::weighted(['fantrax'=>$baseline, '7d'=>$seven, '14d'=>$fourteen, '21d'=>$twentyOne], self::DEFAULT_WEIGHTS);
    }
}
