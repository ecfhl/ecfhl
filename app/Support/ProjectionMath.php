<?php

namespace App\Support;

final class ProjectionMath
{
    public const DEFAULT_WEIGHTS = ['fantrax'=>50.0, 'season'=>0.0, '7d'=>25.0, '14d'=>15.0, '21d'=>10.0];

    public static function weighted(array $rates, array $weights): ?float
    {
        $result = 0.0;
        $total = 0.0;
        foreach (self::DEFAULT_WEIGHTS as $key => $_) {
            if (!isset($rates[$key]) || ($weights[$key] ?? 0) <= 0) continue;
            $result += (float)$rates[$key] * $weights[$key];
            $total += $weights[$key];
        }
        return $total > 0 ? $result / $total : null;
    }

    // The database update uses the same per-player denominator as the preview and collector.
    public static function sql(array $columns, array $weights): string
    {
        $numerator = $denominator = [];
        foreach ($columns as $key => $column) {
            $weight = number_format($weights[$key], 2, '.', '');
            $numerator[] = "COALESCE(($column), 0) * $weight";
            $denominator[] = "CASE WHEN ($column) IS NULL THEN 0 ELSE $weight END";
        }
        return '('.implode(' + ', $numerator).') / NULLIF(('.implode(' + ', $denominator).'), 0)';
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
